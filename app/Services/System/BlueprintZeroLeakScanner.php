<?php

namespace App\Services\System;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

class BlueprintZeroLeakScanner
{
    /**
     * Harte Sperrliste interner Marken-, Personen-, Infrastruktur- und Secret-Begriffe.
     * Sobald auch nur 1 Treffer in Dateinamen oder Inhalten gefunden wird, bricht die Freigabe sofort ab.
     */
    public const FORBIDDEN_TERMS = [
        'seelenfunke',
        'funkenwerk',
        'funkira',
        'falcone',
        'funki',
        'alina',
        'steinhauer',
        'gifhorn',
        'mittwald',
        'p-g27wim',
        '192.168.',
        'xtool',
        'sk_live_',
        'sk_test_',
        'whsec_',
    ];

    /**
     * Prüft ein komplettes Verzeichnis rekursiv (Dateinamen + Inhalte) gegen die Sperrliste.
     *
     * @return array{clean: bool, hits_count: int, scanned_files: int, violations: array<int, array{entry: string, term: string, location: string}>}
     */
    public function scanDirectory(string $directoryPath): array
    {
        if (!is_dir($directoryPath)) {
            return [
                'clean' => false,
                'hits_count' => 1,
                'scanned_files' => 0,
                'violations' => [
                    ['entry' => $directoryPath, 'term' => 'directory_not_found', 'location' => 'filesystem'],
                ],
            ];
        }

        $violations = [];
        $scannedFiles = 0;
        $basePrefix = rtrim(str_replace('\\', '/', realpath($directoryPath) ?: $directoryPath), '/') . '/';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directoryPath, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $scannedFiles++;
            $fullPath = str_replace('\\', '/', $file->getPathname());
            $relPath = str_starts_with($fullPath, $basePrefix)
                ? substr($fullPath, strlen($basePrefix))
                : $file->getFilename();

            if (strtolower($file->getExtension()) === 'zip') {
                $nested = $this->scanZipFile($file->getPathname());
                foreach ($nested['violations'] as $v) {
                    $violations[] = [
                        'entry' => $relPath . '::' . $v['entry'],
                        'term' => $v['term'],
                        'location' => $v['location'],
                    ];
                }
                $this->collectStringViolations($relPath, '', $violations, true);
                continue;
            }

            $content = (string) @file_get_contents($file->getPathname());
            $this->collectStringViolations($relPath, $content, $violations);
        }

        return [
            'clean' => count($violations) === 0,
            'hits_count' => count($violations),
            'scanned_files' => $scannedFiles,
            'violations' => $violations,
        ];
    }

    /**
     * Prüft ein .zip-Archiv im Speicher (alle Entry-Pfade + Dateiinhalte) gegen die Sperrliste.
     *
     * @return array{clean: bool, hits_count: int, scanned_files: int, violations: array<int, array{entry: string, term: string, location: string}>}
     */
    public function scanZipFile(string $zipAbsolutePath): array
    {
        if (!is_file($zipAbsolutePath) || !is_readable($zipAbsolutePath)) {
            return [
                'clean' => false,
                'hits_count' => 1,
                'scanned_files' => 0,
                'violations' => [
                    ['entry' => basename($zipAbsolutePath), 'term' => 'zip_not_found', 'location' => 'archive'],
                ],
            ];
        }

        $violations = [];
        // Prüfe auch den Dateinamen des Archivs selbst
        $this->collectStringViolations(basename($zipAbsolutePath), '', $violations, true);

        $zip = new ZipArchive();
        $opened = $zip->open($zipAbsolutePath, ZipArchive::RDONLY);
        if ($opened !== true || $zip->numFiles <= 0) {
            if ($opened === true) {
                $zip->close();
            }
            return [
                'clean' => false,
                'hits_count' => count($violations) + 1,
                'scanned_files' => 0,
                'violations' => array_merge($violations, [
                    ['entry' => basename($zipAbsolutePath), 'term' => 'invalid_or_empty_zip', 'location' => 'archive'],
                ]),
            ];
        }

        $scannedFiles = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = (string) $zip->getNameIndex($i);
            if (str_ends_with($entryName, '/')) {
                $this->collectStringViolations($entryName, '', $violations, true);
                continue;
            }

            $scannedFiles++;
            $content = (string) $zip->getFromIndex($i);
            $this->collectStringViolations($entryName, $content, $violations);
        }

        $zip->close();

        return [
            'clean' => count($violations) === 0,
            'hits_count' => count($violations),
            'scanned_files' => $scannedFiles,
            'violations' => $violations,
        ];
    }

    /**
     * Wirft sofort eine RuntimeException, falls im ZIP mindestens ein Sperrbegriff gefunden wird.
     */
    public function assertZipIsClean(string $zipAbsolutePath): array
    {
        $report = $this->scanZipFile($zipAbsolutePath);
        if (!$report['clean'] || $report['hits_count'] > 0) {
            $terms = implode(', ', array_unique(array_column($report['violations'], 'term')));
            throw new RuntimeException(
                "ZERO-LEAK SECURITY BLOCK: Paket-Freigabe verweigert ({$report['hits_count']} Sperrtreffer: {$terms})."
            );
        }

        return $report;
    }

    /**
     * Wirft sofort eine RuntimeException, falls im Verzeichnis mindestens ein Sperrbegriff gefunden wird.
     */
    public function assertDirectoryIsClean(string $directoryPath): array
    {
        $report = $this->scanDirectory($directoryPath);
        if (!$report['clean'] || $report['hits_count'] > 0) {
            $terms = implode(', ', array_unique(array_column($report['violations'], 'term')));
            throw new RuntimeException(
                "ZERO-LEAK SECURITY BLOCK: Verzeichnis-Freigabe verweigert ({$report['hits_count']} Sperrtreffer: {$terms})."
            );
        }

        return $report;
    }

    /**
     * @param array<int, array{entry: string, term: string, location: string}> $violations
     */
    private function collectStringViolations(
        string $entryName,
        string $content,
        array &$violations,
        bool $nameOnly = false
    ): void {
        foreach (self::FORBIDDEN_TERMS as $term) {
            if (stripos($entryName, $term) !== false) {
                $violations[] = [
                    'entry' => $entryName,
                    'term' => $term,
                    'location' => 'filename',
                ];
            }

            if (!$nameOnly && $content !== '' && stripos($content, $term) !== false) {
                $violations[] = [
                    'entry' => $entryName,
                    'term' => $term,
                    'location' => 'content',
                ];
            }
        }
    }
}
