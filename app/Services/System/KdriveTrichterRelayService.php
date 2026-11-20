<?php

namespace App\Services\System;

use App\Services\AI\VertexAiService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class KdriveTrichterRelayService
{
    public const MAX_BYTES = 26214400; // 25 MB

    public const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt', 'csv', 'docx', 'xlsx', 'odt', 'ods',
    ];

    public const DEFAULT_TARGET_FOLDERS = [
        '01_Mein-Seelenfunke',
        '02_Finanzen_Steuern_Banken',
        '03_Haus_Immobilie',
        '04_Versicherungen_Vertraege_Mobilitaet',
        '05_Familie_Ausweise_Vorsorge',
        '06_Gesundheit',
        '07_Behoerden_Recht_Berufshistorie',
        '08_Tresor_Backups',
    ];

    public static function relayDirectory(): string
    {
        $dir = app()->runningUnitTests()
            ? storage_path('framework/testing/kdrive_trichter_relay')
            : storage_path('app/private/kdrive_trichter_relay');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0700, true, true);
        }
        return $dir;
    }

    public static function isValidRelayId(string $id): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id));
    }

    public static function purgeExpired(int $maxAgeDays = 30): void
    {
        $dir = self::relayDirectory();
        $cutoff = time() - ($maxAgeDays * 86400);

        foreach (File::glob($dir . '/*.json') ?: [] as $metaFile) {
            try {
                if (@filemtime($metaFile) < $cutoff) {
                    $id = pathinfo($metaFile, PATHINFO_FILENAME);
                    self::ackAndDelete($id);
                }
            } catch (\Throwable) {
            }
        }
    }

    public static function countPending(): int
    {
        $dir = self::relayDirectory();
        $files = File::glob($dir . '/*.json');
        return is_array($files) ? count($files) : 0;
    }

    public static function enqueueUploadedFile(
        UploadedFile $file,
        ?string $customTitle = null,
        string $source = 'android_scanner',
        ?int $batchIndex = null,
        ?int $batchTotal = null
    ): array {
        self::purgeExpired();

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Ungültige Dateigröße (maximal 25 MB erlaubt).');
        }

        $origName = (string) $file->getClientOriginalName();
        $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $mime = strtolower((string) $file->getMimeType());
            $ext = match ($mime) {
                'application/pdf' => 'pdf',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'text/plain' => 'txt',
                default => '',
            };
        }

        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Dateityp nicht für den kDrive Eingang-Trichter freigegeben.');
        }

        $rawBytes = @file_get_contents($file->getRealPath());
        if ($rawBytes === false || $rawBytes === '') {
            throw new \RuntimeException('Hochgeladene Datei konnte nicht gelesen werden.');
        }

        return self::enqueueRawBytes(
            $rawBytes,
            $origName !== '' ? $origName : ('Scan_' . date('Y-m-d_His') . '.' . $ext),
            (string) ($file->getMimeType() ?: 'application/octet-stream'),
            $customTitle,
            $source,
            $batchIndex,
            $batchTotal
        );
    }

    public static function enqueueRawBytes(
        string $rawBytes,
        string $originalName,
        string $mimeType = 'application/pdf',
        ?string $customTitle = null,
        string $source = 'android_scanner',
        ?int $batchIndex = null,
        ?int $batchTotal = null
    ): array {
        $len = strlen($rawBytes);
        if ($len <= 0 || $len > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Ungültige Dateigröße.');
        }

        $ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Ungültige Dateiendung.');
        }

        $cleanTitle = $customTitle !== null
            ? trim((string) preg_replace('/[\r\n\t]+/', ' ', $customTitle))
            : '';
        $cleanTitle = mb_substr($cleanTitle, 0, 100);

        $origStem = (string) pathinfo($originalName, PATHINFO_FILENAME);
        $cleanStem = self::buildSafeStem($origStem, $cleanTitle, $batchIndex, $batchTotal);
        $safeFileName = $cleanStem . '.' . $ext;

        $id = (string) Str::uuid();
        $dir = self::relayDirectory();
        $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';
        $metaPath = $dir . DIRECTORY_SEPARATOR . $id . '.json';

        $encryptedPayload = Crypt::encryptString(base64_encode($rawBytes));
        $sha256 = hash('sha256', $rawBytes);
        $sequenceMs = (int) round(microtime(true) * 1000);

        $meta = [
            'id' => $id,
            'file_name' => $safeFileName,
            'custom_title' => $cleanTitle,
            'mime_type' => $mimeType,
            'extension' => $ext,
            'size_bytes' => $len,
            'sha256' => $sha256,
            'source' => $source,
            'sequence_ms' => $sequenceMs,
            'created_at' => now('Europe/Berlin')->toIso8601String(),
        ];

        File::put($encPath, $encryptedPayload, true);
        @chmod($encPath, 0600);
        File::put($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), true);
        @chmod($metaPath, 0600);

        return $meta;
    }

    private static function buildSafeStem(
        string $origStem,
        string $cleanTitle,
        ?int $batchIndex = null,
        ?int $batchTotal = null
    ): string {
        $isGenericScan = (bool) preg_match('/^(scan|beleg|bild|dokument|trichter|belegpaket)/i', $origStem);

        if ($cleanTitle !== '' && $isGenericScan) {
            // Strip trailing "(1/5)" if FinanceViewModel appended it
            $baseLabel = trim((string) preg_replace('/\s*\(\d+\s*\/\s*\d+\)\s*$/', '', $cleanTitle));
            $safeLabel = self::sanitizeFilenameSegment($baseLabel);
            if ($safeLabel !== '') {
                $suffix = '';
                if ($batchIndex !== null && $batchTotal !== null && $batchTotal > 1) {
                    $pad = $batchTotal >= 100 ? 3 : 2;
                    $suffix = '_' . str_pad((string) $batchIndex, $pad, '0', STR_PAD_LEFT);
                } elseif (preg_match('/_(\d{2,3})$/', $origStem, $m)) {
                    $suffix = '_' . $m[1];
                } elseif (preg_match('/\((\d+)\s*\/\s*(\d+)\)\s*$/', $cleanTitle, $m)) {
                    $tot = (int) $m[2];
                    $pad = $tot >= 100 ? 3 : 2;
                    $suffix = $tot > 1 ? ('_' . str_pad($m[1], $pad, '0', STR_PAD_LEFT)) : '';
                }
                return mb_substr(date('Y-m-d') . '_' . $safeLabel . $suffix, 0, 95);
            }
        }

        $sanitized = self::sanitizeFilenameSegment($origStem);
        if ($sanitized === '') {
            $sanitized = 'Scan_' . date('Y-m-d_His');
        }
        return mb_substr($sanitized, 0, 95);
    }

    public static function sanitizeFilenameSegment(string $raw): string
    {
        $s = str_replace([' – ', ' — ', '–', '—'], '-', $raw);
        $s = preg_replace('/[^A-Za-z0-9ÄÖÜäöüß._ -]/u', '_', $s);
        $s = preg_replace('/\s+/', '_', (string) $s);
        $s = preg_replace('/_+/', '_', (string) $s);
        return trim((string) $s, ' ._');
    }

    public static function listPending(): array
    {
        self::purgeExpired();
        $dir = self::relayDirectory();
        $items = [];

        foreach (File::glob($dir . '/*.json') ?: [] as $metaPath) {
            try {
                $id = pathinfo($metaPath, PATHINFO_FILENAME);
                if (!self::isValidRelayId($id)) {
                    continue;
                }
                $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';
                if (!File::exists($encPath)) {
                    @unlink($metaPath);
                    continue;
                }
                $decoded = json_decode((string) File::get($metaPath), true);
                if (is_array($decoded)) {
                    $items[] = $decoded;
                }
            } catch (\Throwable) {
            }
        }

        usort($items, static function (array $a, array $b): int {
            $seqA = (int) ($a['sequence_ms'] ?? 0);
            $seqB = (int) ($b['sequence_ms'] ?? 0);
            if ($seqA !== $seqB) {
                return $seqA <=> $seqB;
            }
            return strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? ''));
        });

        return $items;
    }

    public static function pullDecrypted(string $id): ?array
    {
        if (!self::isValidRelayId($id)) {
            return null;
        }

        $dir = self::relayDirectory();
        $metaPath = $dir . DIRECTORY_SEPARATOR . $id . '.json';
        $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';

        if (!File::exists($metaPath) || !File::exists($encPath)) {
            return null;
        }

        try {
            $meta = json_decode((string) File::get($metaPath), true);
            if (!is_array($meta)) {
                return null;
            }

            $decryptedB64 = Crypt::decryptString((string) File::get($encPath));
            $rawBytes = base64_decode($decryptedB64, true);
            if ($rawBytes === false || $rawBytes === '') {
                return null;
            }

            $expectedSha = strtolower((string) ($meta['sha256'] ?? ''));
            if ($expectedSha !== '' && hash('sha256', $rawBytes) !== $expectedSha) {
                return null;
            }

            return [
                'meta' => $meta,
                'bytes' => $rawBytes,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function ackAndDelete(string $id): bool
    {
        if (!self::isValidRelayId($id)) {
            return false;
        }

        $dir = self::relayDirectory();
        $metaPath = $dir . DIRECTORY_SEPARATOR . $id . '.json';
        $encPath = $dir . DIRECTORY_SEPARATOR . $id . '.enc';

        $existed = File::exists($metaPath) || File::exists($encPath);
        if (File::exists($encPath)) {
            @unlink($encPath);
        }
        if (File::exists($metaPath)) {
            @unlink($metaPath);
        }

        return $existed;
    }

    public static function classifyDocumentWithAi(UploadedFile $file, array $availableFolders = []): array
    {
        $folders = array_values(array_filter(
            array_map(static fn($f) => trim((string) $f), $availableFolders),
            static fn(string $f) => $f !== '' && !str_starts_with($f, '00_') && !str_starts_with($f, '.')
        ));
        if (empty($folders)) {
            $folders = self::DEFAULT_TARGET_FOLDERS;
        }

        $origName = (string) $file->getClientOriginalName();
        $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION)) ?: 'pdf';
        $mime = strtolower((string) ($file->getMimeType() ?: 'application/pdf'));
        $today = now('Europe/Berlin')->format('Y-m-d');

        $fallback = self::classifyFallbackHeuristic($origName, $ext, $folders, $today);

        if (app()->runningUnitTests()) {
            return $fallback;
        }

        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath) || filesize($realPath) > 15 * 1024 * 1024) {
            return $fallback;
        }

        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'text/plain', 'text/csv'], true)) {
            return $fallback;
        }

        try {
            $rawBytes = @file_get_contents($realPath);
            if ($rawBytes === false || $rawBytes === '') {
                return $fallback;
            }
            $base64 = base64_encode($rawBytes);
            $folderListStr = implode(', ', $folders);

            $prompt = <<<PROMPT
Du bist der intelligente Dokumenten-Archivar für Alina Steinhauer (Einzelunternehmen Mein-Seelenfunke + Privatarchiv).
Analysiere dieses gescannte Dokument und ordne es exakt EINEM der folgenden vorhandenen Zielordner zu:
[{$folderListStr}]

Ordner-Regeln:
- "01_Mein-Seelenfunke": Alle geschäftlichen Unterlagen, Lieferantenrechnungen (PrintEquipment, Glücksfieber, Westpack, Kartonfritze, xTool, Verpackung, Laser, Shop, Hosting, Gewerbe).
- "02_Finanzen_Steuern_Banken": Bankunterlagen, Kontoauszüge, Steuerbescheide, Finanzamt, ELSTER, private Rechnungen/Quittungen ohne Bezug zu Mein-Seelenfunke.
- "03_Haus_Immobilie": Haus, Miete, Grundsteuer, Strom, Gas, Wasser, Schornsteinfeger, Handwerker am Haus, Internet/Festnetz daheim.
- "04_Versicherungen_Vertraege_Mobilitaet": Versicherungen (Haftpflicht, ARAG, Rechtsschutz, Hausrat, KFZ), Mobilfunkverträge, Auto/Mobilität.
- "05_Familie_Ausweise_Vorsorge": Ausweise, Urkunden, Vollmachten, Rente, Vorsorge, Familie.
- "06_Gesundheit": Arztbriefe, Befunde, Krankenkasse (BKK firmus), Rezepte, Atteste, Apotheke, Kliniken.
- "07_Behoerden_Recht_Berufshistorie": Behördenbriefe, Agentur für Arbeit, Gründungszuschuss, Gericht, frühere Arbeitgeber, Zeugnisse.
- "08_Tresor_Backups": Recovery-Codes, Lizenzschlüssel, Sicherheits-Backups.

Antworte AUSSCHLIESSLICH mit validem JSON ohne Markdown:
{
  "target_folder": "Exakter Ordnername aus der Liste",
  "document_date": "YYYY-MM-DD (Dokumentdatum oder {$today})",
  "sender": "Kurzer Absendername ohne Sonderzeichen (z.B. BKK-firmus, Finanzamt-Gifhorn, PrintEquipment)",
  "subject": "Prägnanter Betreff in 2 bis 4 Wörtern mit Bindestrichen (z.B. Beitragsbescheid, Rechnung-Rohlinge)",
  "summary": "Kurze Zusammenfassung in 1 Satz ohne Gedankenstriche",
  "tags": ["Tag1", "Tag2"]
}
PROMPT;

            $models = [
                'gemini-3-flash-preview' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                    'thinkingConfig' => ['thinkingLevel' => 'LOW'],
                ],
                'gemini-3.1-flash-lite-preview' => [
                    'temperature' => 0.1,
                    'responseMimeType' => 'application/json',
                ],
            ];

            foreach ($models as $model => $genConfig) {
                try {
                    $payload = [
                        'contents' => [[
                            'parts' => [
                                ['text' => $prompt],
                                ['inlineData' => ['mimeType' => $mime, 'data' => $base64]],
                            ],
                        ]],
                        'generationConfig' => $genConfig,
                    ];

                    $response = VertexAiService::generateContent($model, $payload, 20, 5);
                    if (!$response->successful()) {
                        continue;
                    }

                    $parts = $response->json('candidates.0.content.parts') ?? [];
                    foreach ((array) $parts as $part) {
                        if (!is_array($part) || !empty($part['thought']) || empty($part['text'])) {
                            continue;
                        }
                        $cleanJson = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', (string) $part['text']));
                        $parsed = json_decode($cleanJson, true);
                        if (!is_array($parsed)) {
                            continue;
                        }

                        $chosenFolder = trim((string) ($parsed['target_folder'] ?? ''));
                        if (!in_array($chosenFolder, $folders, true)) {
                            $chosenFolder = $fallback['target_folder'];
                        }

                        $docDate = trim((string) ($parsed['document_date'] ?? ''));
                        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate)) {
                            $docDate = $today;
                        }

                        $sender = self::sanitizeFilenameSegment((string) ($parsed['sender'] ?? ''));
                        $subject = self::sanitizeFilenameSegment((string) ($parsed['subject'] ?? ''));
                        $stemParts = array_values(array_filter([$docDate, $sender, $subject], static fn($v) => $v !== ''));
                        $suggestedStem = count($stemParts) >= 2
                            ? mb_substr(implode('_', $stemParts), 0, 95)
                            : $fallback['suggested_stem'];

                        $summary = str_replace([' – ', ' — '], ', ', trim((string) ($parsed['summary'] ?? '')));
                        $tags = is_array($parsed['tags'] ?? null)
                            ? array_values(array_filter(array_map(static fn($t) => trim((string) $t), $parsed['tags'])))
                            : $fallback['tags'];

                        return [
                            'target_folder' => $chosenFolder,
                            'suggested_filename' => $suggestedStem . '.' . $ext,
                            'suggested_stem' => $suggestedStem,
                            'extracted_date' => $docDate,
                            'title' => trim(($sender !== '' ? $sender . ' ' : '') . str_replace('_', ' ', $subject)) ?: $suggestedStem,
                            'category' => str_replace('_', ' ', preg_replace('/^\d+_/', '', $chosenFolder)),
                            'summary' => $summary !== '' ? $summary : $fallback['summary'],
                            'tags' => !empty($tags) ? array_slice($tags, 0, 5) : $fallback['tags'],
                            'ai_classified' => true,
                        ];
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        } catch (\Throwable) {
        }

        return $fallback;
    }

    public static function classifyFallbackHeuristic(string $origName, string $ext, array $folders, string $today): array
    {
        $lower = mb_strtolower($origName);
        $pick = static function (array $candidates) use ($folders): string {
            foreach ($candidates as $cand) {
                foreach ($folders as $f) {
                    if (stripos($f, $cand) !== false) {
                        return $f;
                    }
                }
            }
            return $folders[0] ?? '02_Finanzen_Steuern_Banken';
        };

        $targetFolder = match (true) {
            str_contains($lower, 'seelenfunke') || str_contains($lower, 'xtool') || str_contains($lower, 'westpack') || str_contains($lower, 'printequipment') || str_contains($lower, 'gluecksfieber') || str_contains($lower, 'gewerbe')
                => $pick(['01_Mein-Seelenfunke', 'Mein-Seelenfunke']),
            str_contains($lower, 'bkk') || str_contains($lower, 'arzt') || str_contains($lower, 'befund') || str_contains($lower, 'krank') || str_contains($lower, 'gesundheit') || str_contains($lower, 'rezept')
                => $pick(['06_Gesundheit', 'Gesundheit']),
            str_contains($lower, 'versicherung') || str_contains($lower, 'arag') || str_contains($lower, 'police') || str_contains($lower, 'kfz') || str_contains($lower, 'vertrag')
                => $pick(['04_Versicherungen', 'Versicherungen', 'Vertraege']),
            str_contains($lower, 'haus') || str_contains($lower, 'miete') || str_contains($lower, 'immobilie') || str_contains($lower, 'strom') || str_contains($lower, 'wasser')
                => $pick(['03_Haus', 'Haus_Immobilie', 'Haus']),
            str_contains($lower, 'ausweis') || str_contains($lower, 'urkunde') || str_contains($lower, 'familie') || str_contains($lower, 'vorsorge')
                => $pick(['05_Familie', 'Ausweise', 'Vorsorge']),
            str_contains($lower, 'behoerde') || str_contains($lower, 'gericht') || str_contains($lower, 'arbeitsamt') || str_contains($lower, 'bescheid') || str_contains($lower, 'zeugnis')
                => $pick(['07_Behoerden', 'Behoerden', 'Beruf']),
            str_contains($lower, 'backup') || str_contains($lower, 'tresor') || str_contains($lower, 'recovery') || str_contains($lower, 'keepass')
                => $pick(['08_Tresor', 'Tresor', 'Keepass']),
            default
                => $pick(['02_Finanzen', 'Finanzen', 'Rechnungen', 'Banken']),
        };

        $cleanStem = self::sanitizeFilenameSegment((string) pathinfo($origName, PATHINFO_FILENAME));
        if ($cleanStem === '') {
            $cleanStem = 'Scan_' . date('Y-m-d_His');
        }

        return [
            'target_folder' => $targetFolder,
            'suggested_filename' => $cleanStem . '.' . $ext,
            'suggested_stem' => $cleanStem,
            'extracted_date' => $today,
            'title' => str_replace('_', ' ', $cleanStem),
            'category' => str_replace('_', ' ', preg_replace('/^\d+_/', '', $targetFolder)),
            'summary' => 'Dokument einsortiert in ' . $targetFolder,
            'tags' => [str_replace('_', ' ', preg_replace('/^\d+_/', '', $targetFolder)), strtoupper($ext)],
            'ai_classified' => false,
        ];
    }
}
