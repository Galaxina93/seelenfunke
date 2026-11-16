<?php

namespace App\Services\AI;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use FilesystemIterator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class KnowledgeReportSyncService
{
    public const SOURCE_PREFIX = 'Abschlussberichte/';
    public const MAX_TAGS_PER_REPORT = 15;

    /**
     * Verbindliches Ordner-zu-Kategorie-Mapping für Abschlussberichte/.
     *
     * @var array<string, string>
     */
    public const FOLDER_CATEGORY_MAP = [
        'Agenten' => 'Agenten',
        'Android' => 'Android',
        'Arbeitsdokumentation' => 'Arbeitsdokumentation',
        'Bestellungen' => 'Bestellungen',
        'Blog' => 'Blog',
        'Buchhaltung' => 'Buchhaltung',
        'Dashboard' => 'Dashboard',
        'Konzepte' => 'Konzepte',
        'Leitung' => 'Leitung',
        'Marketing' => 'Marketing',
        'Produkte' => 'Produkte',
        'Prüfprotokoll' => 'Prüfprotokoll',
        'Support' => 'Support',
        'System' => 'System',
        'Workflows' => 'Workflows',
        'ROOT' => 'Übersicht',
    ];

    /**
     * Kuratierte Infrastruktur-, Hardware- & Ökosystem-Schlagwörter.
     *
     * @var array<string, array<int, string>>
     */
    private const HARDWARE_AND_PLATFORM_KEYWORDS = [
        'xtool' => ['xtool', 'f2 ultra'],
        'ra3' => ['ra3', 'rotationsaufsatz'],
        'minipc' => ['minipc', 'mini-pc', 'funkenwerk-os-linux', '192.168.188.151'],
        'mittwald' => ['mittwald', 'stage.mein-seelenfunke.de'],
        'stripe' => ['stripe'],
        'vertex' => ['vertex', 'vertexai', 'gemini'],
        'louvre' => ['louvre', 'halle der helden'],
        'dhl' => ['dhl', 'versandlabel'],
        'dsgvo' => ['dsgvo'],
        'funkenwerk' => ['funkenwerk'],
    ];

    /**
     * Stoppwörter für Dateinamen-Tokenisierung.
     *
     * @var array<string, bool>
     */
    private const FILENAME_STOPWORDS = [
        'und' => true,
        'oder' => true,
        'der' => true,
        'die' => true,
        'das' => true,
        'den' => true,
        'dem' => true,
        'des' => true,
        'fuer' => true,
        'für' => true,
        'mit' => true,
        'von' => true,
        'vom' => true,
        'zum' => true,
        'zur' => true,
        'aus' => true,
        'auf' => true,
        'bei' => true,
        'alle' => true,
        'bericht' => true,
        'abschlussbericht' => true,
        'konzept' => true,
        'workflow' => true,
        'pruefprotokoll' => true,
        'prufprotokoll' => true,
    ];

    /** @var array<string, array{id: string, name: string, slug: string}> */
    private array $categoriesBySlug = [];

    /** @var array<string, array{id: string, name: string, slug: string}> */
    private array $categoriesByName = [];

    /** @var array<string, array{id: string, name: string, slug: string}> */
    private array $tagsBySlug = [];

    /** @var array<string, array{id: string, name: string, slug: string}> */
    private array $tagsByName = [];

    /**
     * Synchronisiert alle Markdown-Dateien aus Abschlussberichte/ idempotent und deterministisch
     * via SHA-256-Delta-Check in AiKnowledgeBase. Manuelle Einträge (source_path IS NULL)
     * bleiben garantiert unangetastet.
     *
     * @return array{
     *     scanned: int,
     *     created: int,
     *     updated: int,
     *     skipped_unchanged: int,
     *     deleted_stale: int,
     *     elapsed_ms: float
     * }
     */
    public function sync(?string $reportsDirectory = null, bool $force = false): array
    {
        $startedAt = microtime(true);

        if (!Schema::hasTable('ai_knowledge_bases') || !Schema::hasColumn('ai_knowledge_bases', 'source_path')) {
            return [
                'scanned' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped_unchanged' => 0,
                'deleted_stale' => 0,
                'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        }

        $rootDir = $reportsDirectory !== null && trim($reportsDirectory) !== ''
            ? rtrim($reportsDirectory, "/\\")
            : base_path('Abschlussberichte');

        if (!is_dir($rootDir)) {
            return [
                'scanned' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped_unchanged' => 0,
                'deleted_stale' => 0,
                'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ];
        }

        $markdownFiles = $this->collectMarkdownFiles($rootDir);

        // Einzige Vorab-Abfrage für den gesamten SHA-256-Delta-Check
        $existingSynced = AiKnowledgeBase::query()
            ->whereNotNull('source_path')
            ->where('source_path', 'like', self::SOURCE_PREFIX . '%')
            ->select([
                'id',
                'title',
                'slug',
                'source_path',
                'content_sha256',
                'ai_knowledge_base_category_id',
                'is_published',
                'visibility_scope',
            ])
            ->get()
            ->keyBy('source_path');

        $seenSourcePaths = [];
        $pendingUpserts = [];
        $skippedUnchanged = 0;

        foreach ($markdownFiles as $relPath => $absPath) {
            $sourcePath = self::SOURCE_PREFIX . $relPath;
            if (strlen($sourcePath) > 191) {
                $sourcePath = substr($sourcePath, 0, 191);
            }
            $seenSourcePaths[$sourcePath] = true;

            $rawBytes = @file_get_contents($absPath);
            if ($rawBytes === false) {
                continue;
            }

            $normalizedContent = str_replace(["\r\n", "\r"], "\n", $rawBytes);
            if (trim($normalizedContent) === '') {
                continue;
            }

            $sha256 = hash('sha256', $normalizedContent);
            $existing = $existingSynced->get($sourcePath);

            if (
                !$force
                && $existing !== null
                && $existing->content_sha256 === $sha256
                && $existing->is_published === true
                && $existing->visibility_scope === AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE
                && !empty($existing->ai_knowledge_base_category_id)
            ) {
                $skippedUnchanged++;
                continue;
            }

            $pendingUpserts[] = [
                'rel_path' => $relPath,
                'source_path' => $sourcePath,
                'content' => $normalizedContent,
                'sha256' => $sha256,
                'existing' => $existing,
            ];
        }

        $staleIds = [];
        foreach ($existingSynced as $storedPath => $row) {
            if (!isset($seenSourcePaths[$storedPath])) {
                $staleIds[] = $row->id;
            }
        }

        $created = 0;
        $updated = 0;
        $deletedStale = 0;

        if (!empty($pendingUpserts) || !empty($staleIds)) {
            $this->warmTaxonomyCaches();
            $nowTs = now();

            // Phase 1: Kategorien, Titel & Tags im Speicher vorbereiten und fehlende Kategorien/Tags gebündelt anlegen
            $preparedRows = [];
            $newCategoryRows = [];
            $newTagRows = [];

            foreach ($pendingUpserts as $item) {
                $relPath = $item['rel_path'];
                $categoryName = $this->resolveCategoryNameFromRelativePath($relPath);
                $catObj = $this->stageCategory($categoryName, $nowTs, $newCategoryRows);
                $title = $this->extractReportTitle($relPath, $item['content']);
                $tagLabels = $this->extractReportTags($relPath, $categoryName, $item['content']);
                $tagIds = $this->stageTags($tagLabels, $nowTs, $newTagRows);

                $preparedRows[] = [
                    'source_path' => $item['source_path'],
                    'content' => $item['content'],
                    'sha256' => $item['sha256'],
                    'existing' => $item['existing'],
                    'category_id' => $catObj['id'],
                    'title' => $title,
                    'tag_ids' => $tagIds,
                ];
            }

            DB::transaction(function () use (
                $preparedRows,
                $newCategoryRows,
                $newTagRows,
                $staleIds,
                $nowTs,
                &$created,
                &$updated,
                &$deletedStale
            ): void {
                if (!empty($staleIds)) {
                    // Strikt doppelt abgesichert: Niemals source_path IS NULL löschen!
                    $deletedStale = AiKnowledgeBase::query()
                        ->whereNotNull('source_path')
                        ->whereIn('id', $staleIds)
                        ->delete();
                }

                if (!empty($newCategoryRows)) {
                    foreach (array_chunk($newCategoryRows, 50) as $catChunk) {
                        DB::table('ai_knowledge_base_categories')->insert($catChunk);
                    }
                }

                if (!empty($newTagRows)) {
                    foreach (array_chunk($newTagRows, 100) as $tagChunk) {
                        DB::table('ai_knowledge_base_tags')->insert($tagChunk);
                    }
                }

                $newKbInserts = [];
                $touchedKbIds = [];
                $pivotInserts = [];

                foreach ($preparedRows as $prep) {
                    /** @var AiKnowledgeBase|null $existing */
                    $existing = $prep['existing'];

                    if ($existing !== null) {
                        $kbId = (string) $existing->id;
                        DB::table('ai_knowledge_bases')
                            ->where('id', $kbId)
                            ->update([
                                'title' => $prep['title'],
                                'ai_knowledge_base_category_id' => $prep['category_id'],
                                'content' => $prep['content'],
                                'content_sha256' => $prep['sha256'],
                                'is_published' => true,
                                'visibility_scope' => AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
                                'updated_at' => $nowTs,
                            ]);
                        $updated++;
                    } else {
                        $kbId = (string) Str::uuid();
                        $newKbInserts[] = [
                            'id' => $kbId,
                            'title' => $prep['title'],
                            'slug' => $this->buildDeterministicSlug($prep['source_path']),
                            'source_path' => $prep['source_path'],
                            'content_sha256' => $prep['sha256'],
                            'ai_knowledge_base_category_id' => $prep['category_id'],
                            'content' => $prep['content'],
                            'is_published' => true,
                            'visibility_scope' => AiKnowledgeBase::VISIBILITY_ADMIN_EXECUTIVE,
                            'created_at' => $nowTs,
                            'updated_at' => $nowTs,
                        ];
                        $created++;
                    }

                    $touchedKbIds[] = $kbId;
                    foreach ($prep['tag_ids'] as $tid) {
                        $pivotInserts[] = [
                            'ai_knowledge_base_id' => $kbId,
                            'ai_knowledge_base_tag_id' => $tid,
                            'created_at' => $nowTs,
                            'updated_at' => $nowTs,
                        ];
                    }
                }

                if (!empty($newKbInserts)) {
                    foreach (array_chunk($newKbInserts, 40) as $kbChunk) {
                        DB::table('ai_knowledge_bases')->insert($kbChunk);
                    }
                }

                if (!empty($touchedKbIds)) {
                    foreach (array_chunk($touchedKbIds, 200) as $idChunk) {
                        DB::table('ai_knowledge_base_ai_knowledge_base_tag')
                            ->whereIn('ai_knowledge_base_id', $idChunk)
                            ->delete();
                    }
                }

                if (!empty($pivotInserts)) {
                    foreach (array_chunk($pivotInserts, 250) as $pivChunk) {
                        DB::table('ai_knowledge_base_ai_knowledge_base_tag')->insert($pivChunk);
                    }
                }
            });

            if (method_exists(AiKnowledgeBase::class, 'flushBrainCache')) {
                AiKnowledgeBase::flushBrainCache();
            }
        }

        return [
            'scanned' => count($seenSourcePaths),
            'created' => $created,
            'updated' => $updated,
            'skipped_unchanged' => $skippedUnchanged,
            'deleted_stale' => $deletedStale,
            'elapsed_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }

    /**
     * Ermittelt die Ziel-Kategorie anhand des ersten Unterordners unterhalb von Abschlussberichte/.
     */
    public function resolveCategoryNameFromRelativePath(string $relativePath): string
    {
        $normalized = ltrim(str_replace('\\', '/', $relativePath), '/');
        $segments = explode('/', $normalized);

        if (count($segments) <= 1) {
            return self::FOLDER_CATEGORY_MAP['ROOT'];
        }

        $topFolder = trim($segments[0]);
        if (isset(self::FOLDER_CATEGORY_MAP[$topFolder])) {
            return self::FOLDER_CATEGORY_MAP[$topFolder];
        }

        foreach (self::FOLDER_CATEGORY_MAP as $folderKey => $mappedLabel) {
            if (mb_strtolower($folderKey) === mb_strtolower($topFolder)) {
                return $mappedLabel;
            }
        }

        return $topFolder !== '' ? $topFolder : self::FOLDER_CATEGORY_MAP['ROOT'];
    }

    /**
     * Extrahiert maximal 15 präzise, deterministische Schlagwörter aus Pfad, Dateinamen und Markdown-Inhalt.
     *
     * @return array<int, string>
     */
    public function extractReportTags(string $relativePath, string $categoryName, string $markdown): array
    {
        $candidates = [];
        $normalizedRel = ltrim(str_replace('\\', '/', $relativePath), '/');
        $filename = pathinfo($normalizedRel, PATHINFO_FILENAME);
        $lowerRel = mb_strtolower($normalizedRel);
        $lowerBody = mb_strtolower($markdown);

        // 1. Dokumenttyp
        if (str_starts_with($lowerRel, 'konzepte/') || str_starts_with(mb_strtolower($filename), 'konzept_')) {
            $candidates[] = 'konzept';
        } elseif (str_starts_with($lowerRel, 'workflows/') || str_starts_with(mb_strtolower($filename), 'workflow_')) {
            $candidates[] = 'workflow';
        } elseif (
            str_starts_with($lowerRel, 'prüfprotokoll/')
            || str_starts_with($lowerRel, 'pruefprotokoll/')
            || str_contains($lowerRel, 'pruefprotokoll')
            || str_contains($lowerRel, 'audit')
        ) {
            $candidates[] = 'pruefprotokoll';
        } else {
            $candidates[] = 'abschlussbericht';
        }

        // 2. Abteilungs- / Kategorie-Tag
        $deptSlug = $this->normalizeGermanSlug($categoryName);
        if ($deptSlug !== '') {
            $candidates[] = $deptSlug;
        }

        // 3. Dateiprefix & prägnante Dateinamen-Stichworte (max. 3)
        $fileTokens = preg_split('/[_\-\s\.]+/u', $filename, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $addedFileTokens = 0;
        foreach ($fileTokens as $tok) {
            $cleanTok = $this->normalizeGermanSlug($tok);
            if (
                $cleanTok === ''
                || strlen($cleanTok) < 3
                || ctype_digit($cleanTok)
                || isset(self::FILENAME_STOPWORDS[$cleanTok])
                || $cleanTok === $deptSlug
            ) {
                continue;
            }
            $candidates[] = $cleanTok;
            $addedFileTokens++;
            if ($addedFileTokens >= 3) {
                break;
            }
        }

        // 4. Hardware- & Plattform-Schlüsselwörter
        foreach (self::HARDWARE_AND_PLATFORM_KEYWORDS as $canonicalTag => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($lowerBody, $needle) || str_contains($lowerRel, $needle)) {
                    $candidates[] = $canonicalTag;
                    break;
                }
            }
        }

        // 5. Erwähnte PHP-/JS-/C#-/Kotlin-Klassen & -Dateien
        if (preg_match_all('/\b([A-Z][A-Za-z0-9]{2,60}(?:Service|Controller|Command|Seeder|Component|Engine|Guard|Helper|Registry))\b/', $markdown, $classMatches)) {
            $uniqueClasses = array_values(array_unique($classMatches[1]));
            foreach (array_slice($uniqueClasses, 0, 4) as $clsName) {
                $candidates[] = $clsName;
            }
        }

        if (preg_match_all('/\b([A-Za-z0-9_\-]{3,55}\.(?:php|js|cs|kt))\b/', $markdown, $fileMatches)) {
            $uniqueFiles = array_values(array_unique($fileMatches[1]));
            foreach (array_slice($uniqueFiles, 0, 4) as $codeFile) {
                $baseCodeName = pathinfo($codeFile, PATHINFO_FILENAME);
                // Nur ergänzen, wenn nicht bereits als Klassenname erfasst
                if (!in_array($baseCodeName, $candidates, true)) {
                    $candidates[] = $codeFile;
                }
            }
        }

        // 6. Erwähnte Admin- & API-Routen (/admin/..., /api/...)
        if (preg_match_all('/(?<![A-Za-z0-9_])(\/(?:admin|api)\/[a-zA-Z0-9_\-\/]{2,48})/', $markdown, $routeMatches)) {
            $uniqueRoutes = [];
            foreach ($routeMatches[1] as $rawRoute) {
                $cleanRoute = rtrim($rawRoute, '/.,;:)`*');
                if (substr_count($cleanRoute, '/') >= 2 && !str_contains($cleanRoute, '*') && strlen($cleanRoute) <= 50) {
                    $uniqueRoutes[$cleanRoute] = true;
                }
            }
            foreach (array_slice(array_keys($uniqueRoutes), 0, 3) as $routeTag) {
                $candidates[] = $routeTag;
            }
        }

        // Deduplizieren nach Slug und hart auf maximal 15 Tags begrenzen
        $finalTags = [];
        $seenSlugs = [];
        foreach ($candidates as $label) {
            $trimmed = trim($label);
            if ($trimmed === '' || mb_strlen($trimmed) > 80) {
                continue;
            }

            $slug = $this->normalizeGermanSlug($trimmed);
            if ($slug === '' || isset($seenSlugs[$slug])) {
                continue;
            }

            $seenSlugs[$slug] = true;
            $finalTags[] = $trimmed;

            if (count($finalTags) >= self::MAX_TAGS_PER_REPORT) {
                break;
            }
        }

        return $finalTags;
    }

    /**
     * Extrahiert den sauberen Dokumenttitel aus der ersten Markdown-H1-Überschrift oder dem Dateinamen.
     */
    public function extractReportTitle(string $relativePath, string $markdown): string
    {
        if (preg_match('/^\s*#\s+(.+)$/m', $markdown, $m)) {
            $heading = trim($m[1]);
            $heading = str_replace('`', '', $heading);
            $heading = preg_replace('/\s[\x{2013}\x{2014}]\s/u', ': ', $heading) ?? $heading;
            $heading = trim(preg_replace('/\s+/', ' ', $heading) ?? $heading);
            if ($heading !== '') {
                return mb_substr($heading, 0, 250);
            }
        }

        $filename = pathinfo($relativePath, PATHINFO_FILENAME);
        $humanized = trim(str_replace(['_', '-'], ' ', $filename));
        return mb_substr($humanized !== '' ? $humanized : 'Abschlussbericht', 0, 250);
    }

    /**
     * Sammelt alle .md-Dateien rekursiv und sortiert nach relativem Pfad.
     *
     * @return array<string, string> Map: relativeUnixPath => absoluteFilePath
     */
    private function collectMarkdownFiles(string $rootDir): array
    {
        $files = [];
        $rootLen = strlen($rootDir) + 1;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getExtension()) !== 'md') {
                continue;
            }

            $fullPath = $fileInfo->getPathname();
            $relPath = str_replace('\\', '/', substr($fullPath, $rootLen));
            $relPath = ltrim($relPath, '/');

            // Versteckte Dateien/Ordner überspringen
            if ($relPath === '' || str_starts_with($relPath, '.') || str_contains($relPath, '/.')) {
                continue;
            }

            $files[$relPath] = $fullPath;
        }

        ksort($files, SORT_STRING);
        return $files;
    }

    private function warmTaxonomyCaches(): void
    {
        $this->categoriesBySlug = [];
        $this->categoriesByName = [];

        foreach (AiKnowledgeBaseCategory::query()->select(['id', 'name', 'slug'])->get() as $cat) {
            $entry = ['id' => (string) $cat->id, 'name' => (string) $cat->name, 'slug' => (string) $cat->slug];
            $this->categoriesBySlug[$entry['slug']] = $entry;
            $this->categoriesByName[mb_strtolower($entry['name'])] = $entry;
        }

        $this->tagsBySlug = [];
        $this->tagsByName = [];

        foreach (AiKnowledgeBaseTag::query()->select(['id', 'name', 'slug'])->get() as $tag) {
            $entry = ['id' => (string) $tag->id, 'name' => (string) $tag->name, 'slug' => (string) $tag->slug];
            $this->tagsBySlug[$entry['slug']] = $entry;
            $this->tagsByName[mb_strtolower($entry['name'])] = $entry;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $newCategoryRows
     * @return array{id: string, name: string, slug: string}
     */
    private function stageCategory(string $categoryName, $nowTs, array &$newCategoryRows): array
    {
        $cleanName = trim($categoryName) !== '' ? trim($categoryName) : 'Übersicht';
        $lowerName = mb_strtolower($cleanName);
        $slug = 'kb-abt-' . $this->normalizeGermanSlug($cleanName);

        if (isset($this->categoriesByName[$lowerName])) {
            return $this->categoriesByName[$lowerName];
        }

        if (isset($this->categoriesBySlug[$slug])) {
            return $this->categoriesBySlug[$slug];
        }

        $baseSlug = Str::slug($cleanName, '-', 'de');
        if ($baseSlug !== '' && isset($this->categoriesBySlug[$baseSlug])) {
            return $this->categoriesBySlug[$baseSlug];
        }

        $id = (string) Str::uuid();
        $entry = ['id' => $id, 'name' => $cleanName, 'slug' => $slug];
        $newCategoryRows[] = [
            'id' => $id,
            'name' => $cleanName,
            'slug' => $slug,
            'created_at' => $nowTs,
            'updated_at' => $nowTs,
        ];

        $this->categoriesBySlug[$slug] = $entry;
        $this->categoriesByName[$lowerName] = $entry;

        return $entry;
    }

    /**
     * @param array<int, string> $tagLabels
     * @param array<int, array<string, mixed>> $newTagRows
     * @return array<int, string>
     */
    private function stageTags(array $tagLabels, $nowTs, array &$newTagRows): array
    {
        $ids = [];

        foreach ($tagLabels as $label) {
            $cleanLabel = trim($label);
            if ($cleanLabel === '') {
                continue;
            }

            $lower = mb_strtolower($cleanLabel);
            $slug = $this->normalizeGermanSlug($cleanLabel);
            if ($slug === '') {
                continue;
            }

            if (isset($this->tagsByName[$lower])) {
                $ids[] = $this->tagsByName[$lower]['id'];
                continue;
            }

            if (isset($this->tagsBySlug[$slug])) {
                $ids[] = $this->tagsBySlug[$slug]['id'];
                continue;
            }

            $id = (string) Str::uuid();
            $entry = ['id' => $id, 'name' => $cleanLabel, 'slug' => $slug];
            $newTagRows[] = [
                'id' => $id,
                'name' => $cleanLabel,
                'slug' => $slug,
                'created_at' => $nowTs,
                'updated_at' => $nowTs,
            ];

            $this->tagsBySlug[$slug] = $entry;
            $this->tagsByName[$lower] = $entry;
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    private function buildDeterministicSlug(string $sourcePath): string
    {
        $withoutExt = preg_replace('/\.md$/i', '', $sourcePath) ?? $sourcePath;
        $base = $this->normalizeGermanSlug($withoutExt);
        if ($base === '') {
            $base = 'abschlussbericht';
        }

        return substr($base, 0, 175) . '-' . substr(hash('sha256', $sourcePath), 0, 10);
    }

    private function normalizeGermanSlug(string $value): string
    {
        $replaced = strtr($value, [
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'ß' => 'ss',
            '/' => '-',
            '.' => '-',
        ]);

        return Str::slug($replaced, '-');
    }
}
