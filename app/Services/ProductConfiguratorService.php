<?php

namespace App\Services;

use App\Models\System\SystemSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public const QUOTES_STORAGE_DIR = 'produkte/configurator/quotes';
    public const QUOTES_MANIFEST_REL_PATH = 'produkte/configurator/quotes/manifest.json';

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

    public const DEFAULT_QUOTE_CATEGORIES = [
        'Liebe & Partnerschaft',
        'Trauer & Gedenken',
        'Hochzeit & Jahrestag',
        'Taufe & Geburt',
        'Familie & Freundschaft',
        'Haustiere & Pfotenliebe',
        'Kurze Gravuren (Schmuck)',
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
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

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
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

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
        \Illuminate\Support\Facades\Cache::forget('global_shop_settings');

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
     * Normalisiert einen relativen Vektor-Pfad (z. B. "Liebe/amor_pfeil.svg" oder "amor_pfeil.svg")
     * und findet bei flachen Dateinamen automatisch den passenden Kategorie-Unterordner.
     */
    public function resolveCanonicalVectorRelPath(string $relativeFile): ?string
    {
        $cleanRel = ltrim(str_replace('\\', '/', trim($relativeFile)), '/');
        if ($cleanRel === '' || str_contains($cleanRel, '..') || str_contains($cleanRel, "\0")) {
            return null;
        }
        if (str_starts_with($cleanRel, 'vectors/')) {
            $cleanRel = ltrim(substr($cleanRel, strlen('vectors/')), '/');
        }
        if ($cleanRel === '' || !str_ends_with(strtolower($cleanRel), '.svg')) {
            return null;
        }

        $storageBase = storage_path('app/public/' . self::VECTORS_STORAGE_DIR);
        $publicBase = public_path('shop/product/configurator/vectors');

        if (is_file($storageBase . '/' . $cleanRel) || is_file($publicBase . '/' . $cleanRel)) {
            return $cleanRel;
        }

        // Fallback für flache Dateinamen ohne Kategorie-Ordner (z. B. "amor_pfeil.svg" -> "Liebe/amor_pfeil.svg")
        $baseName = basename($cleanRel);
        foreach ([$storageBase, $publicBase] as $baseDir) {
            if (!is_dir($baseDir)) {
                continue;
            }
            $entries = @scandir($baseDir);
            if (!is_array($entries)) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                if (is_dir($baseDir . '/' . $entry) && is_file($baseDir . '/' . $entry . '/' . $baseName)) {
                    return $entry . '/' . $baseName;
                }
            }
        }

        return $cleanRel;
    }

    /**
     * Löst die öffentliche Asset-URL für eine Vektor-Datei auf (Storage zuerst, Public als Fallback).
     */
    public function resolveVectorUrl(string $relativeFile): string
    {
        $cleanRel = $this->resolveCanonicalVectorRelPath($relativeFile);
        if ($cleanRel === null) {
            return '';
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
        $cleanRel = $this->resolveCanonicalVectorRelPath($relativeFile);
        if ($cleanRel === null) {
            return null;
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

    // =========================================================================
    // 3. SPRUCH- & WIDMUNGS-BIBLIOTHEK („KREATIV-STARTHILFE“) + KI-GENERATOR
    // =========================================================================

    /**
     * Stellt sicher, dass das Spruch-Manifest unter storage/app/public/produkte/configurator/quotes/manifest.json
     * existiert und mit den Standard-Kategorien sowie kuratierten Seelenfunke-Sprüchen initialisiert ist.
     */
    public function ensureQuotesInitialized(): array
    {
        $disk = Storage::disk('public');
        if (!$disk->exists(self::QUOTES_STORAGE_DIR)) {
            $disk->makeDirectory(self::QUOTES_STORAGE_DIR);
        }

        $manifestPath = storage_path('app/public/' . self::QUOTES_MANIFEST_REL_PATH);
        $manifest = [
            'categories' => self::DEFAULT_QUOTE_CATEGORIES,
            'items'      => [],
        ];

        $isFirstInit = !is_file($manifestPath);

        if (!$isFirstInit) {
            $decoded = json_decode((string) @file_get_contents($manifestPath), true);
            if (is_array($decoded)) {
                $manifest['categories'] = array_values(array_unique(array_filter(
                    is_array($decoded['categories'] ?? null) ? $decoded['categories'] : self::DEFAULT_QUOTE_CATEGORIES,
                    fn ($c) => is_string($c) && trim($c) !== ''
                )));
                $manifest['items'] = is_array($decoded['items'] ?? null) ? $decoded['items'] : [];
            }
        }

        if (empty($manifest['categories'])) {
            $manifest['categories'] = self::DEFAULT_QUOTE_CATEGORIES;
        }

        if ($isFirstInit || empty($manifest['items'])) {
            $seedItems = $this->getDefaultSeedQuotes();
            $manifest['items'] = $seedItems;
            $this->saveQuotesManifest($manifest);
        } else {
            // Aktualisiere unformatierte Standard-Sprüche (seed_*) einmalig auf saubere Mehrzeilen-Umbrüche
            $seedItems = $this->getDefaultSeedQuotes();
            $updatedSeeds = false;
            foreach ($seedItems as $seedId => $seedData) {
                if (
                    isset($manifest['items'][$seedId])
                    && ($manifest['items'][$seedId]['source'] ?? 'default') === 'default'
                    && !str_contains((string) ($manifest['items'][$seedId]['text'] ?? ''), "\n")
                    && str_contains($seedData['text'], "\n")
                ) {
                    $manifest['items'][$seedId]['text'] = $seedData['text'];
                    $updatedSeeds = true;
                }
            }
            if ($updatedSeeds) {
                $this->saveQuotesManifest($manifest);
            }
        }

        return $manifest;
    }

    public function saveQuotesManifest(array $manifest): void
    {
        $manifest['categories'] = array_values(array_unique(array_filter(
            $manifest['categories'] ?? self::DEFAULT_QUOTE_CATEGORIES,
            fn ($c) => is_string($c) && trim($c) !== ''
        )));

        $manifestPath = storage_path('app/public/' . self::QUOTES_MANIFEST_REL_PATH);
        $dir = dirname($manifestPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Liefert alle aktiven (nicht archivierten) Sprüche gruppiert nach Kategorie für den Frontend-Konfigurator.
     */
    public function getActiveQuotesGrouped(): array
    {
        $manifest = $this->ensureQuotesInitialized();
        $categories = $manifest['categories'] ?? self::DEFAULT_QUOTE_CATEGORIES;
        $items = $manifest['items'] ?? [];

        $grouped = [];
        foreach ($categories as $cat) {
            $grouped[$cat] = [];
        }

        foreach ($items as $id => $meta) {
            if (!empty($meta['archived'])) {
                continue;
            }
            $text = trim((string) ($meta['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $cat = $meta['category'] ?? ($categories[0] ?? 'Allgemein');
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }

            $grouped[$cat][] = [
                'id'       => $id,
                'title'    => $meta['title'] ?? '',
                'text'     => $text,
                'category' => $cat,
                'length'   => mb_strlen($text),
            ];
        }

        foreach ($grouped as $cat => &$list) {
            if (empty($list)) {
                unset($grouped[$cat]);
            }
        }
        unset($list);

        return $grouped;
    }

    /**
     * Liefert die komplette Spruch-Datenstruktur für das Admin-Panel (/admin/product-configurator).
     */
    public function getAdminQuotesData(): array
    {
        $manifest = $this->ensureQuotesInitialized();
        $categories = $manifest['categories'] ?? self::DEFAULT_QUOTE_CATEGORIES;
        $items = $manifest['items'] ?? [];

        $quotes = [];
        $activeCount = 0;
        $archivedCount = 0;
        $categoryCounts = array_fill_keys($categories, 0);

        foreach ($items as $id => $meta) {
            $text = trim((string) ($meta['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $cat = $meta['category'] ?? ($categories[0] ?? 'Allgemein');
            if (!isset($categoryCounts[$cat])) {
                $categoryCounts[$cat] = 0;
                $categories[] = $cat;
            }

            $isArchived = (bool) ($meta['archived'] ?? false);
            if ($isArchived) {
                $archivedCount++;
            } else {
                $activeCount++;
                $categoryCounts[$cat]++;
            }

            $quotes[] = [
                'id'         => $id,
                'title'      => trim((string) ($meta['title'] ?? '')),
                'text'       => $text,
                'category'   => $cat,
                'archived'   => $isArchived,
                'source'     => $meta['source'] ?? 'default',
                'length'     => mb_strlen($text),
                'created_at' => $meta['created_at'] ?? null,
            ];
        }

        return [
            'categories'      => array_values(array_unique($categories)),
            'category_counts' => $categoryCounts,
            'quotes'          => $quotes,
            'stats'           => [
                'total'      => count($quotes),
                'active'     => $activeCount,
                'archived'   => $archivedCount,
                'categories' => count($categories),
            ],
        ];
    }

    /**
     * Fügt einen neuen Spruch (manuell oder aus dem KI-Generator übernommen) hinzu.
     */
    public function addQuote(string $text, string $category, string $title = '', string $source = 'manual'): array
    {
        $manifest = $this->ensureQuotesInitialized();
        $cleanText = $this->sanitizeQuoteText($text);
        if ($cleanText === '') {
            throw new \InvalidArgumentException('Bitte gib einen Spruchtext ein.');
        }

        $cleanCategory = trim($category) !== '' ? trim($category) : ($manifest['categories'][0] ?? 'Liebe & Partnerschaft');
        if (!in_array($cleanCategory, $manifest['categories'], true)) {
            $manifest['categories'][] = $cleanCategory;
        }

        $id = 'q_' . substr(md5($cleanCategory . '|' . $cleanText . '|' . microtime(true)), 0, 12);
        $entry = [
            'id'         => $id,
            'title'      => trim(strip_tags($title)),
            'text'       => $cleanText,
            'category'   => $cleanCategory,
            'archived'   => false,
            'source'     => $source,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Vorne einfügen, damit neue Sprüche direkt sichtbar sind
        $manifest['items'] = [$id => $entry] + ($manifest['items'] ?? []);
        $this->saveQuotesManifest($manifest);

        return $entry;
    }

    /**
     * Aktualisiert einen bestehenden Spruch (Text, Titel und/oder Kategorie).
     */
    public function updateQuote(string $id, string $text, string $category, string $title = ''): bool
    {
        $manifest = $this->ensureQuotesInitialized();
        if (!isset($manifest['items'][$id])) {
            return false;
        }

        $cleanText = $this->sanitizeQuoteText($text);
        if ($cleanText === '') {
            return false;
        }

        $cleanCategory = trim($category);
        if ($cleanCategory !== '' && !in_array($cleanCategory, $manifest['categories'], true)) {
            $manifest['categories'][] = $cleanCategory;
        }

        $manifest['items'][$id]['text'] = $cleanText;
        $manifest['items'][$id]['title'] = trim(strip_tags($title));
        if ($cleanCategory !== '') {
            $manifest['items'][$id]['category'] = $cleanCategory;
        }

        $this->saveQuotesManifest($manifest);
        return true;
    }

    /**
     * Schaltet den Archivierungsstatus eines Spruchs um.
     */
    public function toggleArchiveQuote(string $id): ?bool
    {
        $manifest = $this->ensureQuotesInitialized();
        if (!isset($manifest['items'][$id])) {
            return null;
        }

        $newState = empty($manifest['items'][$id]['archived']);
        $manifest['items'][$id]['archived'] = $newState;
        $this->saveQuotesManifest($manifest);

        return $newState;
    }

    /**
     * Löscht einen Spruch dauerhaft aus dem Manifest.
     */
    public function deleteQuote(string $id): bool
    {
        $manifest = $this->ensureQuotesInitialized();
        if (!isset($manifest['items'][$id])) {
            return false;
        }

        unset($manifest['items'][$id]);
        $this->saveQuotesManifest($manifest);
        return true;
    }

    /**
     * Legt eine neue Spruch-Kategorie an.
     */
    public function addQuoteCategory(string $name): bool
    {
        $clean = trim(strip_tags($name));
        if ($clean === '') {
            return false;
        }

        $manifest = $this->ensureQuotesInitialized();
        foreach ($manifest['categories'] as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($clean)) {
                return false;
            }
        }

        $manifest['categories'][] = $clean;
        $this->saveQuotesManifest($manifest);
        return true;
    }

    /**
     * Benennt eine Spruch-Kategorie um und zieht alle zugeordneten Sprüche mit.
     */
    public function renameQuoteCategory(string $oldName, string $newName): bool
    {
        $oldName = trim($oldName);
        $newName = trim(strip_tags($newName));

        if ($oldName === '' || $newName === '' || $oldName === $newName) {
            return false;
        }

        $manifest = $this->ensureQuotesInitialized();
        $idx = array_search($oldName, $manifest['categories'], true);
        if ($idx === false) {
            return false;
        }

        $manifest['categories'][$idx] = $newName;
        $manifest['categories'] = array_values(array_unique($manifest['categories']));

        foreach ($manifest['items'] as &$item) {
            if (($item['category'] ?? '') === $oldName) {
                $item['category'] = $newName;
            }
        }
        unset($item);

        $this->saveQuotesManifest($manifest);
        return true;
    }

    /**
     * Löscht eine Spruch-Kategorie und verschiebt deren Sprüche in eine Zielkategorie.
     */
    public function deleteQuoteCategory(string $categoryName, ?string $fallbackCategory = null): bool
    {
        $manifest = $this->ensureQuotesInitialized();
        if (count($manifest['categories']) <= 1) {
            return false;
        }

        $idx = array_search($categoryName, $manifest['categories'], true);
        if ($idx === false) {
            return false;
        }

        unset($manifest['categories'][$idx]);
        $manifest['categories'] = array_values($manifest['categories']);

        $targetCategory = ($fallbackCategory && in_array($fallbackCategory, $manifest['categories'], true))
            ? $fallbackCategory
            : ($manifest['categories'][0] ?? 'Liebe & Partnerschaft');

        foreach ($manifest['items'] as &$item) {
            if (($item['category'] ?? '') === $categoryName) {
                $item['category'] = $targetCategory;
            }
        }
        unset($item);

        $this->saveQuotesManifest($manifest);
        return true;
    }

    /**
     * Verschiebt eine Spruch-Kategorie in der Sortierreihenfolge nach oben oder unten.
     */
    public function moveQuoteCategoryOrder(string $categoryName, string $direction = 'up'): void
    {
        $manifest = $this->ensureQuotesInitialized();
        $cats = array_values($manifest['categories']);
        $idx = array_search($categoryName, $cats, true);

        if ($idx === false) {
            return;
        }

        $targetIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
        if ($targetIdx < 0 || $targetIdx >= count($cats)) {
            return;
        }

        $tmp = $cats[$targetIdx];
        $cats[$targetIdx] = $cats[$idx];
        $cats[$idx] = $tmp;

        $manifest['categories'] = $cats;
        $this->saveQuotesManifest($manifest);
    }

    /**
     * Generiert per Knopfdruck passende, gravurfähige Sprüche (ohne Emojis) über Gemini KI
     * (mit kuratiertem Kreativ-Fallback, falls der API-Key fehlt oder offline ist).
     *
     * @return array<int, array{title: string, text: string, category: string}>
     */
    public function generateAiQuotes(string $category, string $styleHint = '', int $maxLength = 60, int $count = 5): array
    {
        $category = trim($category) !== '' ? trim($category) : 'Liebe & Partnerschaft';
        $styleHint = trim($styleHint);
        $maxLength = max(20, min(140, $maxLength));
        $count = max(1, min(10, $count));

        $manifest = $this->ensureQuotesInitialized();
        $existingTexts = array_map(
            fn ($i) => mb_strtolower(trim((string) ($i['text'] ?? ''))),
            array_values($manifest['items'] ?? [])
        );

        $apiKey = config('services.gemini.key', env('GEMINI_API_KEY'));
        if (!empty($apiKey)) {
            $hintInstruction = $styleHint !== ''
                ? "Zusätzlicher Wunsch / Anlass / Stil-Hinweis: \"{$styleHint}\"."
                : "Stil: Emotional, edel, zeitlos und perfekt für eine hochwertige Geschenk-Gravur der Manufaktur 'Mein Seelenfunke'.";

            $prompt = <<<PROMPT
Du bist Chef-Texter für 'Mein Seelenfunke', eine deutsche Manufaktur für personalisierte Lasergravur-Unikate (Kristallglas, Schmuckanhänger, Schiefer, Holz).
Erstelle exakt {$count} verschiedene, tiefgründige und sofort gravierbare Sprüche / Widmungen auf Deutsch für die Kategorie "{$category}".
{$hintInstruction}

WICHTIGE REGELN FÜR DIE LASERGRAVUR:
1. Maximale Zeichenlänge pro Spruch: ca. {$maxLength} Zeichen (kurz, prägnant, ästhetisch auf kleiner Fläche).
2. ZEILENUMBRÜCHE (\n): Füge bei Sprüchen ab 22 Zeichen sinnvolle, harmonische Zeilenumbrüche (\n) nach ca. 18–24 Zeichen pro Zeile ein, damit der Spruch zentriert perfekt auf die Gravurfläche passt und nicht über den Rand hinausragt!
3. KEINE Emojis oder Farb-Icons verwenden (ein Laser kann keine Emojis gravieren!). Erlaubt sind normale Satzzeichen, Punkte (•) oder kaufmännisches Und (&).
4. Keine Dubletten zu bestehenden Standardsprüchen.
5. Gib ausschließlich ein valides JSON-Array mit Objekten im Format [{"title": "Kurzer Anlass/Titel (2-3 Wörter)", "text": "Zeile 1\nZeile 2"}] zurück. Kein Markdown, kein Text drumherum.
PROMPT;

            $payload = [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [['text' => $prompt]],
                    ],
                ],
                'generationConfig' => [
                    'temperature'      => 0.75,
                    'responseMimeType' => 'application/json',
                ],
            ];

            foreach (['gemini-2.5-flash', 'gemini-1.5-flash'] as $model) {
                try {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
                    $response = Http::timeout(20)->withHeaders([
                        'x-goog-api-key' => $apiKey,
                        'Content-Type'   => 'application/json',
                    ])->post($url, $payload);

                    if ($response->successful()) {
                        $rawText = $response->json('candidates.0.content.parts.0.text', '[]');
                        $rawText = preg_replace('/^```json\s*|\s*```$/i', '', trim((string) $rawText));
                        $parsed = json_decode((string) $rawText, true);

                        if (is_array($parsed)) {
                            $results = [];
                            foreach ($parsed as $row) {
                                $qText = $this->sanitizeQuoteText((string) ($row['text'] ?? ''));
                                $qTitle = trim(strip_tags((string) ($row['title'] ?? $category)));
                                if ($qText !== '' && !in_array(mb_strtolower($qText), $existingTexts, true)) {
                                    $results[] = [
                                        'title'    => $qTitle,
                                        'text'     => $qText,
                                        'category' => $category,
                                        'length'   => mb_strlen($qText),
                                    ];
                                    $existingTexts[] = mb_strtolower($qText);
                                }
                            }
                            if (!empty($results)) {
                                return array_slice($results, 0, $count);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("ProductConfiguratorService::generateAiQuotes ({$model}) Fallback: " . $e->getMessage());
                }
            }
        }

        // Deterministischer Kreativ-Fallback (falls kein API-Key gesetzt ist oder Offline-Modus)
        return $this->getFallbackGeneratedQuotes($category, $styleHint, $maxLength, $count, $existingTexts);
    }

    /**
     * Bereinigt Spruchtexte für den Laser (keine HTML-Tags, keine Emojis, saubere Zeilenumbrüche).
     */
    public function sanitizeQuoteText(string $value): string
    {
        $clean = strip_tags($value);
        // Entferne Emojis / Pictographs, lasse deutsche Umlaute, Satzzeichen und Zeilenumbrüche intakt
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]/u', '', $clean) ?? $clean;
        $clean = preg_replace("/[ \t]+/u", ' ', $clean) ?? $clean;
        $clean = preg_replace("/\r\n|\r/", "\n", $clean) ?? $clean;
        $clean = trim($clean);

        // Falls ein längerer Spruch noch gar keinen Zeilenumbruch hat, harmonisch umbrechen
        if (!str_contains($clean, "\n") && mb_strlen($clean) > 25) {
            $clean = $this->formatQuoteLineBreaks($clean, 24);
        }

        return $clean;
    }

    /**
     * Bricht zu lange Einzelzeilen harmonisch an Wortgrenzen um (ca. 22–24 Zeichen pro Zeile),
     * damit Sprüche im Konfigurator nicht über die Gravurfläche hinausragen.
     */
    public function formatQuoteLineBreaks(string $text, int $maxLineChars = 24): string
    {
        $rawLines = explode("\n", trim($text));
        $finalLines = [];

        foreach ($rawLines as $rawLine) {
            $line = trim($rawLine);
            if ($line === '' || mb_strlen($line) <= $maxLineChars) {
                if ($line !== '') {
                    $finalLines[] = $line;
                }
                continue;
            }

            $words = preg_split('/\s+/u', $line) ?: [$line];
            $current = '';
            foreach ($words as $word) {
                $candidate = $current === '' ? $word : ($current . ' ' . $word);
                if (mb_strlen($candidate) <= $maxLineChars || $current === '') {
                    $current = $candidate;
                } else {
                    $finalLines[] = $current;
                    $current = $word;
                }
            }
            if ($current !== '') {
                $finalLines[] = $current;
            }
        }

        return implode("\n", $finalLines);
    }

    /**
     * Kuratierte Initial-Bibliothek für die „Kreativ-Starthilfe“ im Produkt-Konfigurator.
     */
    protected function getDefaultSeedQuotes(): array
    {
        $seeds = [
            // 1. Liebe & Partnerschaft
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Seelenverwandt', 'text' => "Du bist mein Lieblingsmensch –\nheute, morgen und für immer."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Heimatgefühl',   'text' => "Wo du bist,\nist mein Zuhause."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Ewige Liebe',    'text' => "Zwei Herzen, ein Takt,\nein gemeinsamer Weg."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Liebesbeweis',   'text' => "Unter Millionen Sternen\nbist du mein hellster Funke."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Zusammenhalt',   'text' => "Hand in Hand –\nein Leben lang."],

            // 2. Trauer & Gedenken
            ['category' => 'Trauer & Gedenken', 'title' => 'Unvergessen',       'text' => "Aus dem Leben bist du uns genommen,\naber nicht aus unseren Herzen."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Ewiges Licht',      'text' => "Was man tief im Herzen besitzt,\nkann man durch den Tod\nnicht verlieren."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Sternenhimmel',     'text' => "Wenn du bei Nacht\nden Himmel anschaust,\nlachen dort alle Sterne."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Stille Erinnerung', 'text' => "Für immer geliebt,\nniemals vergessen,\nauf ewig in unseren Herzen."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Spuren der Liebe',  'text' => "Deine Schritte sind verstummt,\ndoch die Spuren deiner Liebe\nbleiben überall."],

            // 3. Hochzeit & Jahrestag
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Ja-Wort',        'text' => "Ein Ja für den Moment –\nein Versprechen für die Ewigkeit."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Eheglück',       'text' => "Aus Du und Ich\nwurde unser schönstes Wir."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Jahrestag',      'text' => "Jeder Tag mit dir\nist mein liebster Tag."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Lebensreise',    'text' => "Liebe heißt, gemeinsam\nin die gleiche Richtung\nzu blicken."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Für das Brautpaar', 'text' => "Möge eure Liebe jeden Tag\naufs Neue funkeln."],

            // 4. Taufe & Geburt
            ['category' => 'Taufe & Geburt', 'title' => 'Willkommen im Leben', 'text' => "Ein kleines Wunder,\ndas unser ganzes Leben\nhell erleuchtet."],
            ['category' => 'Taufe & Geburt', 'title' => 'Schutzengel-Segen',   'text' => "Mögen Engel dich begleiten\nauf all deinen Wegen."],
            ['category' => 'Taufe & Geburt', 'title' => 'Zur Geburt',          'text' => "Die Sterne, die Blumen\nund das Leuchten\nin deinen Kinderaugen."],
            ['category' => 'Taufe & Geburt', 'title' => 'Wurzeln & Flügel',    'text' => "Wurzeln, die dich halten,\nund Flügel, die dich tragen."],
            ['category' => 'Taufe & Geburt', 'title' => 'Taufspruch',          'text' => "Behütet, geliebt und getragen –\nvom ersten Augenblick an."],

            // 5. Familie & Freundschaft
            ['category' => 'Familie & Freundschaft', 'title' => 'Familienbande', 'text' => "Familie ist da, wo das Leben\nbeginnt und die Liebe\nniemals endet."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Beste Mama',    'text' => "Für die Welt bist du eine Mama,\ndoch für uns bist du\ndie ganze Welt."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Bester Papa',   'text' => "Danke, dass du immer\nunser Fels in der Brandung bist."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Wahre Freunde', 'text' => "Gute Freunde sind wie Sterne:\nMan sieht sie nicht immer,\naber sie sind immer da."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Dankbarkeit',   'text' => "Schön, dass es dich gibt –\ndanke für jeden Moment."],

            // 6. Haustiere & Pfotenliebe
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Pfotenabdruck',    'text' => "Kleine Pfoten hinterlassen\ndie größten Spuren\nin unseren Herzen."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Regenbogenbrücke', 'text' => "Bis wir uns hinter der\nRegenbogenbrücke wiedersehen,\nkleiner Freund."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Treue Seele',      'text' => "Treue hat vier Pfoten\nund ein Herz aus Gold."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Seelenhund',       'text' => "Ein Seelenhund bleibt\nfür immer an deiner Seite."],

            // 7. Kurze Gravuren (Schmuck)
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Klassiker',       'text' => "Für immer im Herzen"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Verbundenheit',   'text' => "Für immer & ewig"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Seelenfunke',     'text' => "Mein kleiner\nSeelenfunke"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Glücksbringer',   'text' => "Dein Schutzengel"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Unendlichkeit',   'text' => "Unendlich geliebt"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Erinnerung',      'text' => "Unvergessen"],
        ];

        $items = [];
        foreach ($seeds as $idx => $seed) {
            $id = 'seed_' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $items[$id] = [
                'id'         => $id,
                'title'      => $seed['title'],
                'text'       => $seed['text'],
                'category'   => $seed['category'],
                'archived'   => false,
                'source'     => 'default',
                'created_at' => '2026-01-01 00:00:00',
            ];
        }

        return $items;
    }

    protected function getFallbackGeneratedQuotes(string $category, string $styleHint, int $maxLength, int $count, array $existingTexts): array
    {
        $pool = [
            'Liebe & Partnerschaft' => [
                ['title' => 'Herzensmensch',   'text' => "Mit dir wird jeder Augenblick\nzu einem Funken Ewigkeit."],
                ['title' => 'Für immer Wir',   'text' => "Du bist mein Anfang,\nmein Glück und mein Zuhause."],
                ['title' => 'Seelenanker',     'text' => "In deinen Armen liegt\nmein schönstes Zuhause."],
                ['title' => 'Liebesfunken',    'text' => "Meine Liebe zu dir wächst\nmit jedem Herzschlag."],
                ['title' => 'Sternstunde',     'text' => "Unter tausend Wegen würde ich\nimmer wieder deinen wählen."],
            ],
            'Trauer & Gedenken' => [
                ['title' => 'Stilles Licht',   'text' => "Ein Seelenfunke erlischt nie –\ner leuchtet im Herzen weiter."],
                ['title' => 'In Liebe',        'text' => "Du fehlst uns jeden Tag,\ndoch deine Liebe trägt uns weiter."],
                ['title' => 'Himmelsstern',    'text' => "Jetzt leuchtest du als hellster Stern\nüber unseren Wegen."],
                ['title' => 'Ewige Nähe',      'text' => "Dem Auge fern, dem Herzen\nfür alle Zeit ganz nah."],
                ['title' => 'Dankbare Stille', 'text' => "Danke für jedes Lächeln\nund jede Stunde mit dir."],
            ],
            'Hochzeit & Jahrestag' => [
                ['title' => 'Lebensbund',      'text' => "Zwei Seelen, ein Versprechen,\neine gemeinsame Zukunft."],
                ['title' => 'Liebesglück',     'text' => "Heute, morgen und an jedem Tag\nunseres gemeinsamen Lebens."],
                ['title' => 'Gemeinsamer Weg', 'text' => "Das schönste Kapitel unserer\nGeschichte beginnt genau hier."],
                ['title' => 'Ewiges Band',     'text' => "Verbunden in Liebe, Vertrauen\nund gemeinsamen Träumen."],
                ['title' => 'Jubiläum',        'text' => "Danke für all die wundervollen\nJahre an meiner Seite."],
            ],
            'Taufe & Geburt' => [
                ['title' => 'Lebenslicht',     'text' => "Möge dein Lebensweg immer\nvoll Wärme und Zuversicht sein."],
                ['title' => 'Kleiner Stern',   'text' => "Folge deinem Herzen und leuchte\nauf deine eigene Weise."],
                ['title' => 'Gottes Segen',    'text' => "Geborgen in Liebe auf all\ndeinen künftigen Wegen."],
                ['title' => 'Glückskind',      'text' => "Mit dir ist die Welt ein Stück\nheller und wärmer geworden."],
                ['title' => 'Beschützt',       'text' => "Ein Schutzengel an deiner Seite –\nheute und für immer."],
            ],
            'Familie & Freundschaft' => [
                ['title' => 'Herzensband',     'text' => "Zeit vergeht, doch echte\nVerbundenheit bleibt für immer."],
                ['title' => 'Lebensmensch',    'text' => "Danke, dass du mein Leben\nso viel heller machst."],
                ['title' => 'Zusammenhalt',    'text' => "Gemeinsam stark – durch jeden\nSturm und jeden Sonnenschein."],
                ['title' => 'Familienherz',    'text' => "Unsere Wurzeln geben uns Halt,\nunsere Liebe gibt uns Kraft."],
                ['title' => 'Freundschaft',    'text' => "Wahre Freundschaft ist ein Schatz,\nder nie seinen Glanz verliert."],
            ],
            'Haustiere & Pfotenliebe' => [
                ['title' => 'Pfotenherz',      'text' => "Vier Pfoten, die mein Herz\nfür immer erobert haben."],
                ['title' => 'Treuer Begleiter','text' => "Danke für deine bedingungslose\nTreue an jedem einzelnen Tag."],
                ['title' => 'Sternenpfote',    'text' => "Dein Platz in unserem Herzen\nbleibt für immer dein Zuhause."],
                ['title' => 'Lieblingstier',   'text' => "Du warst nicht nur ein Tier,\ndu warst Familie."],
                ['title' => 'Für immer treu',  'text' => "Spuren im Sand verwehen,\nSpuren im Herzen bleiben."],
            ],
            'Kurze Gravuren (Schmuck)' => [
                ['title' => 'Kurz & Edel',     'text' => "Immer bei dir"],
                ['title' => 'Herzenswort',     'text' => "Aus tiefstem Herzen"],
                ['title' => 'Lebensmotto',     'text' => "Glaube • Liebe • Hoffnung"],
                ['title' => 'Erinnerung',      'text' => "Für alle Zeit"],
                ['title' => 'Talisman',        'text' => "Mut & Zuversicht"],
            ],
        ];

        $candidates = $pool[$category] ?? $pool['Liebe & Partnerschaft'];
        $results = [];

        foreach ($candidates as $cand) {
            $text = $cand['text'];
            if ($styleHint !== '' && mb_strlen($text) + mb_strlen($styleHint) + 3 <= $maxLength) {
                $title = ucfirst(mb_substr($styleHint, 0, 24));
            } else {
                $title = $cand['title'];
            }
            if (mb_strlen($text) > $maxLength && $maxLength <= 40) {
                $shortPool = $pool['Kurze Gravuren (Schmuck)'];
                $shortCand = $shortPool[count($results) % count($shortPool)];
                $text = $shortCand['text'];
                $title = $shortCand['title'];
            }

            if (!in_array(mb_strtolower($text), $existingTexts, true)) {
                $results[] = [
                    'title'    => $title,
                    'text'     => $text,
                    'category' => $category,
                    'length'   => mb_strlen($text),
                ];
            }

            if (count($results) >= $count) {
                break;
            }
        }

        if (empty($results)) {
            foreach ($candidates as $cand) {
                $results[] = [
                    'title'    => $cand['title'],
                    'text'     => $cand['text'],
                    'category' => $category,
                    'length'   => mb_strlen($cand['text']),
                ];
                if (count($results) >= $count) {
                    break;
                }
            }
        }

        return $results;
    }
}
