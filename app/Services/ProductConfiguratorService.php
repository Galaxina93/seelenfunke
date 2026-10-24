<?php

namespace App\Services;

use App\Models\System\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductConfiguratorService
{
    public const TUTORIAL_STORAGE_DIR = 'produkte/configurator/tutorial';
    public const DEFAULT_TUTORIAL_FILENAME = 'configurator-tutorial-default.mp4';
    public const SETTING_VIDEO_PATH = 'configurator_tutorial_video_path';
    public const SETTING_VIDEO_ENABLED = 'configurator_tutorial_enabled';

    public const VECTORS_STORAGE_DIR = 'produkte/configurator/vectors';
    public const MANIFEST_REL_PATH = 'produkte/configurator/vectors/manifest.json';

    public const DEFAULT_CATEGORIES = [
        'Liebe',
        'Familie',
        'Tiere',
        'Natur',
        'Symbole',
        'Ornamente',
        'Feiertage',
        'Allgemein',
    ];

    // =========================================================================
    // 1. TUTORIAL-VIDEO VERWALTUNG (storage/app/public/produkte/configurator/tutorial)
    // =========================================================================

    /**
     * Liefert die aktuelle Konfiguration und Datei-Metadaten des Konfigurator-Tutorials.
     */
    public function getTutorialConfig(): array
    {
        $defaultRelPath = self::TUTORIAL_STORAGE_DIR . '/' . self::DEFAULT_TUTORIAL_FILENAME;

        $savedPath = SystemSetting::where('key', self::SETTING_VIDEO_PATH)->value('value');
        $enabledVal = SystemSetting::where('key', self::SETTING_VIDEO_ENABLED)->value('value');
        $enabled = $enabledVal === null ? true : ($enabledVal !== '0' && $enabledVal !== 'false');

        $cleanSaved = $savedPath ? ltrim(str_replace('\\', '/', (string) $savedPath), '/') : '';
        if (
            $cleanSaved === ''
            || str_contains($cleanSaved, '..')
            || str_contains($cleanSaved, "\0")
            || !Str::startsWith($cleanSaved, self::TUTORIAL_STORAGE_DIR . '/')
            || !in_array(strtolower(pathinfo($cleanSaved, PATHINFO_EXTENSION)), ['mp4', 'webm', 'mov'], true)
        ) {
            $activeRelPath = $defaultRelPath;
        } else {
            $activeRelPath = $cleanSaved;
        }
        $diskPath = storage_path('app/public/' . ltrim($activeRelPath, '/'));

        // Fallback auf Standard-Video, falls ein benutzerdefinierter Pfad nicht mehr existiert
        if (!is_file($diskPath) && $activeRelPath !== $defaultRelPath) {
            $fallbackDiskPath = storage_path('app/public/' . $defaultRelPath);
            if (is_file($fallbackDiskPath)) {
                $activeRelPath = $defaultRelPath;
                $diskPath = $fallbackDiskPath;
            }
        }

        $exists = is_file($diskPath);
        $sizeBytes = $exists ? (int) @filesize($diskPath) : 0;
        $mtime = $exists ? (int) @filemtime($diskPath) : 0;

        return [
            'enabled'        => $enabled,
            'path'           => $activeRelPath,
            'storage_path'   => 'storage/app/public/' . $activeRelPath,
            'url'            => $exists ? asset('storage/' . $activeRelPath) . '?v=' . $mtime : null,
            'exists'         => $exists,
            'is_custom'      => $activeRelPath !== $defaultRelPath,
            'has_default'    => is_file(storage_path('app/public/' . $defaultRelPath)),
            'filename'       => basename($activeRelPath),
            'size_bytes'     => $sizeBytes,
            'size_formatted' => $this->formatBytes($sizeBytes),
            'updated_at'     => $mtime ? date('d.m.Y H:i', $mtime) : null,
        ];
    }

    /**
     * Speichert ein neues Tutorial-Video unter storage/app/public/produkte/configurator/tutorial
     * und entfernt ggf. ein vorheriges benutzerdefiniertes Video (behält aber immer das Default-Video).
     */
    public function storeTutorialVideo(UploadedFile $file): array
    {
        $disk = Storage::disk('public');
        if (!$disk->exists(self::TUTORIAL_STORAGE_DIR)) {
            $disk->makeDirectory(self::TUTORIAL_STORAGE_DIR);
        }

        $defaultRelPath = self::TUTORIAL_STORAGE_DIR . '/' . self::DEFAULT_TUTORIAL_FILENAME;
        $oldPath = SystemSetting::where('key', self::SETTING_VIDEO_PATH)->value('value');

        $mime = strtolower((string) $file->getMimeType());
        $allowedMimes = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ];
        if (!isset($allowedMimes[$mime])) {
            throw new \InvalidArgumentException('Ungültiger Video-MIME-Typ.');
        }

        $realPath = $file->getRealPath();
        if ($realPath && is_file($realPath) && filesize($realPath) > 0 && function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo ? strtolower((string) finfo_file($finfo, $realPath)) : '';
            if ($finfo) {
                finfo_close($finfo);
            }
            if ($detectedMime !== '' && !isset($allowedMimes[$detectedMime]) && $detectedMime !== 'application/octet-stream') {
                throw new \InvalidArgumentException('Ungültiger Video-Dateiinhalt.');
            }
        }

        $ext = strtolower($file->getClientOriginalExtension() ?: $allowedMimes[$mime]);
        if (!in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            $ext = $allowedMimes[$mime];
        }

        $newFilename = 'configurator-tutorial-' . now()->format('Ymd-His') . '.' . $ext;
        $storedRelPath = $file->storeAs(self::TUTORIAL_STORAGE_DIR, $newFilename, 'public');

        // Altes benutzerdefiniertes Video aufräumen (niemals das Default-Video löschen)
        if ($oldPath && $oldPath !== $defaultRelPath && $oldPath !== $storedRelPath && Str::startsWith($oldPath, self::TUTORIAL_STORAGE_DIR . '/')) {
            $disk->delete($oldPath);
        }

        SystemSetting::updateOrCreate(
            ['key' => self::SETTING_VIDEO_PATH],
            ['value' => $storedRelPath]
        );

        return $this->getTutorialConfig();
    }

    /**
     * Setzt das Tutorial-Video wieder auf das mitgelieferte Standard-Video zurück.
     */
    public function resetToDefaultTutorialVideo(): array
    {
        $disk = Storage::disk('public');
        $defaultRelPath = self::TUTORIAL_STORAGE_DIR . '/' . self::DEFAULT_TUTORIAL_FILENAME;
        $currentPath = SystemSetting::where('key', self::SETTING_VIDEO_PATH)->value('value');

        if ($currentPath && $currentPath !== $defaultRelPath && Str::startsWith($currentPath, self::TUTORIAL_STORAGE_DIR . '/')) {
            $disk->delete($currentPath);
        }

        SystemSetting::updateOrCreate(
            ['key' => self::SETTING_VIDEO_PATH],
            ['value' => $defaultRelPath]
        );

        return $this->getTutorialConfig();
    }

    /**
     * Aktiviert oder deaktiviert die Anzeige des Tutorial-Buttons im Produktkonfigurator-Startscreen.
     */
    public function setTutorialEnabled(bool $enabled): bool
    {
        SystemSetting::updateOrCreate(
            ['key' => self::SETTING_VIDEO_ENABLED],
            ['value' => $enabled ? '1' : '0']
        );

        return $enabled;
    }

    // =========================================================================
    // 2. SYMBOL-BIBLIOTHEK VERWALTUNG (storage/app/public/produkte/configurator/vectors)
    // =========================================================================

    /**
     * Stellt sicher, dass das Storage-Verzeichnis und die manifest.json existieren
     * und alle bestehenden Vektoren aus public/shop/product/configurator/vectors übernommen wurden.
     */
    public function ensureVectorsInitialized(): array
    {
        $storageVectorsRoot = storage_path('app/public/' . self::VECTORS_STORAGE_DIR);
        $publicVectorsRoot = public_path('shop/product/configurator/vectors');
        $manifestDiskPath = storage_path('app/public/' . self::MANIFEST_REL_PATH);

        if (!File::isDirectory($storageVectorsRoot)) {
            File::makeDirectory($storageVectorsRoot, 0755, true);
        }

        $manifest = null;
        if (is_file($manifestDiskPath)) {
            $decoded = json_decode((string) file_get_contents($manifestDiskPath), true);
            if (is_array($decoded) && isset($decoded['categories'], $decoded['items'])) {
                $manifest = $decoded;
            }
        }

        $needsSave = false;
        if (!$manifest) {
            $manifest = [
                'categories' => self::DEFAULT_CATEGORIES,
                'items'      => [],
            ];
            $needsSave = true;
        }

        // 1. Kopiere beim Initialisieren fehlende SVGs aus public/shop/product/configurator/vectors nach storage/app/public/produkte/configurator/vectors
        if (File::isDirectory($publicVectorsRoot)) {
            foreach (File::allFiles($publicVectorsRoot) as $pFile) {
                if (strtolower($pFile->getExtension()) !== 'svg') {
                    continue;
                }
                $relPath = str_replace('\\', '/', $pFile->getRelativePathname());
                $targetStorageFile = $storageVectorsRoot . '/' . $relPath;
                if (!is_file($targetStorageFile)) {
                    $targetDir = dirname($targetStorageFile);
                    if (!File::isDirectory($targetDir)) {
                        File::makeDirectory($targetDir, 0755, true);
                    }
                    @copy($pFile->getPathname(), $targetStorageFile);
                }
            }
        }

        // 2. Scanne storage/app/public/produkte/configurator/vectors auf neue Dateien, die noch nicht im Manifest sind
        $existingByFile = [];
        foreach ($manifest['items'] as $idx => $item) {
            $existingByFile[$item['file']] = $idx;
        }

        if (File::isDirectory($storageVectorsRoot)) {
            foreach (File::allFiles($storageVectorsRoot) as $sFile) {
                if (strtolower($sFile->getExtension()) !== 'svg') {
                    continue;
                }
                $relPath = str_replace('\\', '/', $sFile->getRelativePathname());
                if (!isset($existingByFile[$relPath])) {
                    $relDir = str_replace('\\', '/', $sFile->getRelativePath());
                    $category = $relDir ? ucwords(str_replace(['-', '_'], ' ', $relDir)) : 'Allgemein';

                    if (!in_array($category, $manifest['categories'], true)) {
                        $manifest['categories'][] = $category;
                    }

                    $rawName = pathinfo($sFile->getFilename(), PATHINFO_FILENAME);
                    $manifest['items'][] = [
                        'id'          => substr(md5($relPath), 0, 12),
                        'file'        => $relPath,
                        'name'        => $this->formatDefaultVectorTitle($rawName),
                        'category'    => $category,
                        'is_archived' => false,
                        'created_at'  => date('Y-m-d H:i:s', @filemtime($sFile->getPathname()) ?: time()),
                    ];
                    $needsSave = true;
                }
            }
        }

        // 3. Bereinige Manifest-Einträge, deren Datei physisch nicht mehr existiert
        $cleanedItems = [];
        foreach ($manifest['items'] as $item) {
            $fileDiskPath = $storageVectorsRoot . '/' . $item['file'];
            $fallbackPublicPath = $publicVectorsRoot . '/' . $item['file'];
            if (is_file($fileDiskPath) || is_file($fallbackPublicPath)) {
                $cleanedItems[] = $item;
            } else {
                $needsSave = true;
            }
        }
        $manifest['items'] = $cleanedItems;

        if ($needsSave) {
            $this->saveManifest($manifest);
        }

        return $manifest;
    }

    /**
     * Speichert das Symbol-Manifest in storage/app/public/produkte/configurator/vectors/manifest.json.
     */
    public function saveManifest(array $manifest): void
    {
        $manifestDiskPath = storage_path('app/public/' . self::MANIFEST_REL_PATH);
        $dir = dirname($manifestDiskPath);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        file_put_contents(
            $manifestDiskPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Gibt alle aktiven (nicht archivierten) Symbole nach Kategorie gruppiert für den Produktkonfigurator zurück.
     */
    public function getActiveVectorsGrouped(): array
    {
        $manifest = $this->ensureVectorsInitialized();
        $categories = $manifest['categories'] ?? self::DEFAULT_CATEGORIES;
        $grouped = [];

        foreach ($categories as $cat) {
            $grouped[$cat] = [];
        }

        foreach ($manifest['items'] as $item) {
            if (!empty($item['is_archived'])) {
                continue;
            }

            $cat = $item['category'] ?? 'Allgemein';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }

            $grouped[$cat][] = [
                'id'       => $item['id'],
                'file'     => $item['file'],
                'name'     => $item['name'],
                'category' => $cat,
                'url'      => $this->resolveVectorUrl($item['file']),
            ];
        }

        // Entferne leere Kategorien in der Kundenansicht, damit keine leeren Tabs im Konfigurator stehen
        foreach ($grouped as $cat => $items) {
            if (empty($items)) {
                unset($grouped[$cat]);
            }
        }

        return $grouped;
    }

    /**
     * Liefert die gefilterte Symbol-Liste + Statistiken für die Admin-Verwaltung unter /admin/product-configurator.
     */
    public function getAdminVectorsData(?string $categoryFilter = null, string $search = '', string $statusFilter = 'all'): array
    {
        $manifest = $this->ensureVectorsInitialized();
        $categories = $manifest['categories'] ?? self::DEFAULT_CATEGORIES;
        $allItems = $manifest['items'] ?? [];

        $totalCount = count($allItems);
        $activeCount = 0;
        $archivedCount = 0;
        $categoryCounts = array_fill_keys($categories, 0);

        foreach ($allItems as $item) {
            $cat = $item['category'] ?? 'Allgemein';
            if (!isset($categoryCounts[$cat])) {
                $categoryCounts[$cat] = 0;
            }
            $categoryCounts[$cat]++;

            if (!empty($item['is_archived'])) {
                $archivedCount++;
            } else {
                $activeCount++;
            }
        }

        $searchNorm = mb_strtolower(trim($search));
        $filtered = [];

        foreach ($allItems as $item) {
            $isArchived = !empty($item['is_archived']);

            if ($statusFilter === 'active' && $isArchived) {
                continue;
            }
            if ($statusFilter === 'archived' && !$isArchived) {
                continue;
            }
            if ($categoryFilter && $categoryFilter !== 'all' && ($item['category'] ?? 'Allgemein') !== $categoryFilter) {
                continue;
            }
            if ($searchNorm !== '') {
                $haystack = mb_strtolower(($item['name'] ?? '') . ' ' . ($item['file'] ?? '') . ' ' . ($item['category'] ?? ''));
                if (!str_contains($haystack, $searchNorm)) {
                    continue;
                }
            }

            $diskPath = $this->resolveVectorDiskPath($item['file']);
            $sizeKb = ($diskPath && is_file($diskPath)) ? round(max(0.1, filesize($diskPath) / 1024), 1) : 1.0;

            $filtered[] = array_merge($item, [
                'url'     => $this->resolveVectorUrl($item['file']),
                'size_kb' => $sizeKb,
            ]);
        }

        return [
            'categories'      => $categories,
            'category_counts' => $categoryCounts,
            'items'           => $filtered,
            'stats'           => [
                'total'      => $totalCount,
                'active'     => $activeCount,
                'archived'   => $archivedCount,
                'categories' => count($categories),
            ],
        ];
    }

    /**
     * Lädt eine neue SVG-Datei in die gewählte Kategorie hoch (inkl. Sicherheits-Bereinigung & Mirroring).
     */
    public function uploadSymbol(UploadedFile $file, string $category, ?string $customName = null): array
    {
        $manifest = $this->ensureVectorsInitialized();
        $category = trim($category) ?: 'Allgemein';

        if (!in_array($category, $manifest['categories'], true)) {
            $manifest['categories'][] = $category;
        }

        $rawOrigName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slugBase = Str::slug($customName ?: $rawOrigName, '_');
        if ($slugBase === '') {
            $slugBase = 'symbol_' . strtolower(Str::random(6));
        }

        $catFolder = $this->sanitizeCategoryFolder($category);
        $storageVectorsRoot = storage_path('app/public/' . self::VECTORS_STORAGE_DIR);
        $publicVectorsRoot = public_path('shop/product/configurator/vectors');

        $targetDirStorage = $storageVectorsRoot . '/' . $catFolder;
        $targetDirPublic = $publicVectorsRoot . '/' . $catFolder;

        if (!File::isDirectory($targetDirStorage)) {
            File::makeDirectory($targetDirStorage, 0755, true);
        }
        if (!File::isDirectory($targetDirPublic)) {
            File::makeDirectory($targetDirPublic, 0755, true);
        }

        $filename = $slugBase . '.svg';
        $counter = 1;
        while (is_file($targetDirStorage . '/' . $filename)) {
            $filename = $slugBase . '_' . $counter . '.svg';
            $counter++;
        }

        $clientExt = strtolower((string) $file->getClientOriginalExtension());
        if ($clientExt !== '' && $clientExt !== 'svg') {
            throw new \InvalidArgumentException('Nur SVG-Dateien sind in der Symbol-Bibliothek erlaubt.');
        }

        $svgContent = (string) file_get_contents($file->getRealPath());
        $cleanSvg = $this->sanitizeSvgContent($svgContent);
        if (
            $cleanSvg === ''
            || (!preg_match('/<svg\b[^>]*>.*<\/svg\s*>/is', $cleanSvg) && !preg_match('/<svg\b[^>]*\/\s*>/is', $cleanSvg))
        ) {
            throw new \InvalidArgumentException('Die hochgeladene Datei enthält kein gültiges SVG-Dokument.');
        }

        $relFile = $catFolder . '/' . $filename;
        file_put_contents($targetDirStorage . '/' . $filename, $cleanSvg);
        @file_put_contents($targetDirPublic . '/' . $filename, $cleanSvg);

        $displayName = trim((string) $customName) !== ''
            ? trim((string) $customName)
            : $this->formatDefaultVectorTitle($rawOrigName);

        $newItem = [
            'id'          => substr(md5($relFile . microtime(true)), 0, 12),
            'file'        => $relFile,
            'name'        => $displayName,
            'category'    => $category,
            'is_archived' => false,
            'created_at'  => now()->format('Y-m-d H:i:s'),
        ];

        array_unshift($manifest['items'], $newItem);
        $this->saveManifest($manifest);

        return $newItem;
    }

    /**
     * Aktualisiert Anzeigename und Kategorie eines bestehenden Symbols.
     */
    public function updateSymbol(string $id, string $newName, string $newCategory): bool
    {
        $manifest = $this->ensureVectorsInitialized();
        $newName = trim($newName);
        $newCategory = trim($newCategory) ?: 'Allgemein';

        if (!in_array($newCategory, $manifest['categories'], true)) {
            $manifest['categories'][] = $newCategory;
        }

        foreach ($manifest['items'] as &$item) {
            if (($item['id'] ?? '') === $id) {
                if ($newName !== '') {
                    $item['name'] = $newName;
                }
                $item['category'] = $newCategory;
                $this->saveManifest($manifest);
                return true;
            }
        }

        return false;
    }

    /**
     * Archiviert oder reaktiviert ein Symbol (archivierte Symbole sind im Konfigurator ausgeblendet,
     * bleiben aber für bestehende Vorlagen & Bestellungen physisch erhalten).
     */
    public function toggleArchiveSymbol(string $id): ?bool
    {
        $manifest = $this->ensureVectorsInitialized();

        foreach ($manifest['items'] as &$item) {
            if (($item['id'] ?? '') === $id) {
                $item['is_archived'] = empty($item['is_archived']);
                $newState = $item['is_archived'];
                $this->saveManifest($manifest);
                return $newState;
            }
        }

        return null;
    }

    /**
     * Löscht ein Symbol endgültig aus dem Storage, dem Public-Mirror und dem Manifest.
     */
    public function deleteSymbol(string $id): bool
    {
        $manifest = $this->ensureVectorsInitialized();
        $storageVectorsRoot = storage_path('app/public/' . self::VECTORS_STORAGE_DIR);
        $publicVectorsRoot = public_path('shop/product/configurator/vectors');

        foreach ($manifest['items'] as $idx => $item) {
            if (($item['id'] ?? '') === $id) {
                $relFile = ltrim(str_replace('\\', '/', (string) ($item['file'] ?? '')), '/');
                if ($relFile !== '' && !str_contains($relFile, '..') && !str_contains($relFile, "\0")) {
                    @unlink($storageVectorsRoot . '/' . $relFile);
                    @unlink($publicVectorsRoot . '/' . $relFile);
                }

                unset($manifest['items'][$idx]);
                $manifest['items'] = array_values($manifest['items']);
                $this->saveManifest($manifest);
                return true;
            }
        }

        return false;
    }

    /**
     * Legt eine neue Symbol-Kategorie an.
     */
    public function addCategory(string $categoryName): bool
    {
        $manifest = $this->ensureVectorsInitialized();
        $cleanName = trim($categoryName);
        if ($cleanName === '') {
            return false;
        }

        foreach ($manifest['categories'] as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($cleanName)) {
                return false;
            }
        }

        $manifest['categories'][] = $cleanName;
        $catFolder = $this->sanitizeCategoryFolder($cleanName);
        $dir = storage_path('app/public/' . self::VECTORS_STORAGE_DIR . '/' . $catFolder);
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $this->saveManifest($manifest);
        return true;
    }

    /**
     * Benennt eine bestehende Symbol-Kategorie um.
     */
    public function renameCategory(string $oldName, string $newName): bool
    {
        $manifest = $this->ensureVectorsInitialized();
        $newName = trim($newName);
        if ($newName === '' || $oldName === $newName) {
            return false;
        }

        $found = false;
        foreach ($manifest['categories'] as &$cat) {
            if ($cat === $oldName) {
                $cat = $newName;
                $found = true;
            }
        }

        if (!$found) {
            return false;
        }

        foreach ($manifest['items'] as &$item) {
            if (($item['category'] ?? '') === $oldName) {
                $item['category'] = $newName;
            }
        }

        $this->saveManifest($manifest);
        return true;
    }

    /**
     * Löscht eine Kategorie und verschiebt eventuell enthaltene Symbole nach "Allgemein".
     */
    public function deleteCategory(string $categoryName): bool
    {
        $manifest = $this->ensureVectorsInitialized();
        if ($categoryName === 'Allgemein' || count($manifest['categories']) <= 1) {
            return false;
        }

        $manifest['categories'] = array_values(array_filter(
            $manifest['categories'],
            fn($c) => $c !== $categoryName
        ));

        if (!in_array('Allgemein', $manifest['categories'], true)) {
            $manifest['categories'][] = 'Allgemein';
        }

        foreach ($manifest['items'] as &$item) {
            if (($item['category'] ?? '') === $categoryName) {
                $item['category'] = 'Allgemein';
            }
        }

        $this->saveManifest($manifest);
        return true;
    }

    /**
     * Verschiebt eine Kategorie in der Sortierreihenfolge nach links/oben ('up') oder rechts/unten ('down').
     */
    public function moveCategoryOrder(string $categoryName, string $direction): bool
    {
        $manifest = $this->ensureVectorsInitialized();
        $cats = array_values($manifest['categories']);
        $index = array_search($categoryName, $cats, true);
        if ($index === false) {
            return false;
        }

        $targetIndex = $direction === 'up' ? $index - 1 : $index + 1;
        if ($targetIndex < 0 || $targetIndex >= count($cats)) {
            return false;
        }

        $tmp = $cats[$targetIndex];
        $cats[$targetIndex] = $cats[$index];
        $cats[$index] = $tmp;

        $manifest['categories'] = $cats;
        $this->saveManifest($manifest);
        return true;
    }

    /**
     * Löst die öffentliche Asset-URL für eine Vektor-Datei auf (Storage zuerst, Public als Fallback).
     */
    public function resolveVectorUrl(string $relativeFile): string
    {
        $cleanRel = ltrim(str_replace('\\', '/', $relativeFile), '/');
        if (str_contains($cleanRel, '..') || str_contains($cleanRel, "\0")) {
            return '';
        }
        if (str_starts_with($cleanRel, 'vectors/')) {
            $cleanRel = substr($cleanRel, strlen('vectors/'));
        }

        $storageDiskPath = storage_path('app/public/' . self::VECTORS_STORAGE_DIR . '/' . $cleanRel);
        if (is_file($storageDiskPath)) {
            return asset('storage/' . self::VECTORS_STORAGE_DIR . '/' . $cleanRel) . '?v=' . @filemtime($storageDiskPath);
        }

        $publicDiskPath = public_path('shop/product/configurator/vectors/' . $cleanRel);
        if (is_file($publicDiskPath)) {
            return asset('shop/product/configurator/vectors/' . $cleanRel) . '?v=' . @filemtime($publicDiskPath);
        }

        return asset('storage/' . self::VECTORS_STORAGE_DIR . '/' . $cleanRel);
    }

    /**
     * Löst den lokalen Dateisystempfad einer Vektor-Datei auf.
     */
    public function resolveVectorDiskPath(string $relativeFile): ?string
    {
        $cleanRel = ltrim(str_replace('\\', '/', $relativeFile), '/');
        if (str_contains($cleanRel, '..') || str_contains($cleanRel, "\0")) {
            return null;
        }
        if (str_starts_with($cleanRel, 'vectors/')) {
            $cleanRel = substr($cleanRel, strlen('vectors/'));
        }

        $storageRoot = realpath(storage_path('app/public/' . self::VECTORS_STORAGE_DIR));
        $storageDiskPath = storage_path('app/public/' . self::VECTORS_STORAGE_DIR . '/' . $cleanRel);
        if (is_file($storageDiskPath)) {
            $realStorage = realpath($storageDiskPath);
            if ($realStorage !== false && ($storageRoot === false || str_starts_with($realStorage, $storageRoot . DIRECTORY_SEPARATOR))) {
                return $realStorage;
            }
        }

        $publicRoot = realpath(public_path('shop/product/configurator/vectors'));
        $publicDiskPath = public_path('shop/product/configurator/vectors/' . $cleanRel);
        if (is_file($publicDiskPath)) {
            $realPublic = realpath($publicDiskPath);
            if ($realPublic !== false && ($publicRoot === false || str_starts_with($realPublic, $publicRoot . DIRECTORY_SEPARATOR))) {
                return $realPublic;
            }
        }

        return null;
    }

    /**
     * Bereinigt SVG-Dateien beim Upload von potenziell unsicheren Skript-Tags, XXE-Entities, ForeignObjects und Event-Handlern.
     */
    public function sanitizeSvgContent(string $svg): string
    {
        // 1. Strip XML DOCTYPE / ENTITY declarations (XXE / Billion Laughs protection)
        $svg = preg_replace('/<!DOCTYPE[^>]*(\[.*?\])?\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<!ENTITY[^>]*>/is', '', $svg) ?? $svg;

        // 2. Strip dangerous paired and self-closing tags (<script>, <foreignObject>, <iframe>, <object>, <embed>, <applet>, <meta>, <link>, <base>, <handler>, <use>, <image>, <feImage>, <animate*>, <set>)
        $dangerousTags = 'script|foreignObject|iframe|object|embed|applet|meta|link|base|handler|use|image|feImage|animate|animateTransform|animateMotion|set';
        $svg = preg_replace('/<\s*(' . $dangerousTags . ')\b[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $svg) ?? $svg;
        $svg = preg_replace('/<\s*(' . $dangerousTags . ')\b[^>]*\/?\s*>/is', '', $svg) ?? $svg;

        // 3. Strip all inline event handlers (quoted and unquoted: onload=..., onerror=..., onbegin=..., etc.)
        $svg = preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? $svg;

        // 4. Strip @import and external/non-fragment url(...) in CSS styles
        $svg = preg_replace('/@import\b[^;>}]*;?/i', '', $svg) ?? $svg;
        $svg = preg_replace('/url\(\s*["\']?\s*(?!#)[^)]*\)/i', 'none', $svg) ?? $svg;

        // 5. Neutralize any non-fragment URIs in href, xlink:href, or src, and dangerous schemes in values/from/to
        $svg = preg_replace('/\b((?:xlink:)?href|src)\s*=\s*(["\'])\s*(?!#)[^"\']*\2/i', '$1=$2#$2', $svg) ?? $svg;
        $svg = preg_replace('/\b((?:xlink:)?href|src)\s*=\s*(?![\'"]|#)[^\s>]+/i', '$1="#"', $svg) ?? $svg;
        $svg = preg_replace('/\b(values|from|to)\s*=\s*(["\'])\s*(?:javascript|vbscript|data)\s*:[^"\']*\2/i', '$1=$2$2', $svg) ?? $svg;
        $svg = preg_replace('/\b(values|from|to)\s*=\s*(?:javascript|vbscript|data)\s*:[^\s>]*/i', '$1=""', $svg) ?? $svg;

        return trim($svg);
    }

    protected function sanitizeCategoryFolder(string $category): string
    {
        $clean = str_replace(['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü', 'ß'], ['ae', 'oe', 'ue', 'Ae', 'Oe', 'Ue', 'ss'], $category);
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '_', $clean) ?: 'Allgemein';
        return ucfirst($clean);
    }

    public function formatDefaultVectorTitle(string $rawName): string
    {
        $legacyMap = [
            'heart_black'             => 'Herz (Klassisch)',
            'forever_black'           => 'Für immer (Klassisch)',
            'party_smile-white_black' => 'Party-Smiley',
            'baby_black'              => 'Baby (Klassisch)',
            'babygirl_black'          => 'Baby-Mädchen (Klassisch)',
            'flower'                  => 'Blume (Klassisch)',
            'beer'                    => 'Bierkrug (Klassisch)',
            'drink_black'             => 'Anstoßen (Klassisch)',
            'footprint_black'         => 'Fußabdruck (Klassisch)',
        ];

        if (isset($legacyMap[$rawName])) {
            return $legacyMap[$rawName];
        }

        return app(ConfiguratorService::class)->formatVectorTitle($rawName);
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 KB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
        }
        return number_format(max(1, $bytes / 1024), 0, ',', '.') . ' KB';
    }
}
