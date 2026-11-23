<?php

namespace App\Services\AI\Workspace;

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class AbschlussberichteIndexerService
{
    /**
     * Indexiert alle Markdown-Dateien aus base_path('Abschlussberichte') rekursiv in die AiKnowledgeBase.
     *
     * @param bool $force Wenn true, werden alle Dateien neu indexiert (unabhängig vom Hash)
     * @param callable|null $progressCallback Optionaler Callback für Fortschrittsmeldungen: fn(string $msg)
     * @return array{total: int, created: int, updated: int, skipped: int, categories: int}
     */
    public function syncAll(bool $force = false, ?callable $progressCallback = null): array
    {
        $baseDir = base_path('Abschlussberichte');

        if (!File::isDirectory($baseDir)) {
            $syncRes = app(\App\Services\AI\KnowledgeReportSyncService::class)->sync(null, $force);
            $catCount = AiKnowledgeBaseCategory::count();
            return [
                'total' => $syncRes['scanned'],
                'created' => $syncRes['created'],
                'updated' => $syncRes['updated'],
                'skipped' => $syncRes['skipped_unchanged'],
                'categories' => $catCount,
            ];
        }

        $allFiles = File::allFiles($baseDir);
        $createdCount = 0;
        $updatedCount = 0;
        $skippedCount = 0;
        $totalFiles = 0;

        $categoryCache = [];

        foreach ($allFiles as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $totalFiles++;
            $pathname = $file->getPathname();
            $rawContent = File::get($pathname);

            // Relativer Pfad zur Basis
            $relativePath = ltrim(str_replace(['\\', $baseDir], ['/', ''], $pathname), '/');

            // 1. Abteilung / Kategorie ermitteln
            $pathParts = explode('/', $relativePath);
            $department = count($pathParts) > 1 ? $pathParts[0] : 'Allgemein';
            $categorySlug = Str::slug('abschlussberichte-' . $department);
            $categoryName = 'Abschlussberichte: ' . ucfirst($department);

            if (!isset($categoryCache[$categorySlug])) {
                $categoryCache[$categorySlug] = AiKnowledgeBaseCategory::firstOrCreate(
                    ['slug' => $categorySlug],
                    ['name' => $categoryName]
                );
            }
            $category = $categoryCache[$categorySlug];

            // 2. Titel extrahieren
            $title = $this->extractTitle($rawContent, $file->getFilename(), $department);

            // 3. Eindeutiger Slug auf Basis des relativen Pfads
            $pathWithoutExt = preg_replace('/\.md$/i', '', $relativePath);
            $slug = 'abschlussbericht-' . Str::slug(str_replace('/', '-', (string) $pathWithoutExt));
            $slug = mb_substr($slug, 0, 250);

            // 4. SHA256-Hash ermitteln & Schnell-Prüfung
            $fileHash = hash('sha256', $rawContent);
            $cacheKey = 'akb_report_hash_' . md5($relativePath);
            $cachedHash = Cache::get($cacheKey);

            if (!$force && $cachedHash === $fileHash) {
                $skippedCount++;
                continue;
            }

            $existing = AiKnowledgeBase::where('slug', $slug)->first();

            if (!$force && $existing && str_contains((string) $existing->content, "<!-- sha256:{$fileHash} -->")) {
                Cache::forever($cacheKey, $fileHash);
                $skippedCount++;
                continue;
            }

            // 5. Tags ermitteln
            $tags = $this->determineTags($department, $pathParts);

            // 6. Content mit Hash vorbereiten
            $contentToSave = rtrim($rawContent) . "\n\n<!-- sha256:{$fileHash} -->";

            if ($existing) {
                $existing->update([
                    'title' => $title,
                    'ai_knowledge_base_category_id' => $category->id,
                    'content' => $contentToSave,
                    'is_published' => true,
                ]);
                $this->syncTags($existing, $tags);
                $updatedCount++;
                if ($progressCallback) {
                    $progressCallback("Aktualisiert: {$title} ({$relativePath})");
                }
            } else {
                $newKb = AiKnowledgeBase::create([
                    'title' => $title,
                    'slug' => $slug,
                    'ai_knowledge_base_category_id' => $category->id,
                    'content' => $contentToSave,
                    'is_published' => true,
                ]);
                $this->syncTags($newKb, $tags);
                $createdCount++;
                if ($progressCallback) {
                    $progressCallback("Neu angelegt: {$title} ({$relativePath})");
                }
            }

            Cache::forever($cacheKey, $fileHash);
        }

        // Globalen Support- und Such-Cache invalidieren, falls Änderungen stattfanden
        if ($createdCount > 0 || $updatedCount > 0) {
            Cache::forget('ai_kb_published_support');
        }

        return [
            'total' => $totalFiles,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'categories' => count($categoryCache),
        ];
    }

    /**
     * Extrahiert den Titel aus dem ersten H1 '# ' oder fällt auf den Dateinamen zurück.
     */
    protected function extractTitle(string $content, string $filename, string $department): string
    {
        $lines = explode("\n", $content);
        $title = null;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '# ') && !str_starts_with($trimmed, '## ')) {
                $rawH1 = trim(substr($trimmed, 2));
                // Markdown-Formatierungen wie Fettschrift und Backticks entfernen
                $cleanH1 = trim(str_replace(['**', '__', '`', '*', '_'], '', $rawH1));
                if (!empty($cleanH1)) {
                    $title = $cleanH1;
                    break;
                }
            }
        }

        if (!$title) {
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $cleanName = trim(str_replace(['_', '-'], ' ', $nameWithoutExt));
            $title = $cleanName;
        }

        // Kein KI-Gedankenstrich
        $title = str_replace([' – ', ' — '], ': ', $title);

        // Sicherstellen, dass Abschlussberichte als solche betitelt sind
        $lowerTitle = mb_strtolower($title);
        if (!str_contains($lowerTitle, 'abschlussbericht') && !str_contains($lowerTitle, 'readme') && !str_contains($lowerTitle, 'workflow')) {
            $title = "Abschlussbericht: {$title}";
        }

        return mb_substr($title, 0, 250);
    }

    /**
     * Bestimmt semantische Tags für den Abschlussbericht.
     */
    protected function determineTags(string $department, array $pathParts): array
    {
        $tags = ['abschlussbericht', 'rag_system'];

        $deptSlug = Str::slug($department);
        if (!empty($deptSlug)) {
            $tags[] = $deptSlug;
        }

        // Subordner als Tags hinzufügen
        for ($i = 1; $i < count($pathParts) - 1; $i++) {
            $subSlug = Str::slug($pathParts[$i]);
            if (!empty($subSlug) && !in_array($subSlug, $tags, true)) {
                $tags[] = $subSlug;
            }
        }

        return array_unique($tags);
    }

    /**
     * Synchronisiert Tags für einen AiKnowledgeBase-Eintrag.
     */
    protected function syncTags(AiKnowledgeBase $kb, array $tags): void
    {
        $tagIds = [];
        foreach ($tags as $tagName) {
            $tagNameClean = trim($tagName);
            if (empty($tagNameClean)) {
                continue;
            }
            $slug = Str::slug($tagNameClean);
            $tag = AiKnowledgeBaseTag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $tagNameClean]
            );
            $tagIds[] = $tag->id;
        }

        $kb->tags()->sync($tagIds);
    }
}
