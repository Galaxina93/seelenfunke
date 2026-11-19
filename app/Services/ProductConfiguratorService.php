<?php

namespace App\Services;

use App\Models\Product\Product;
use App\Models\Product\ProductTemplate;
use App\Models\System\SystemSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
        'Familie & Freundschaft',
        'Bibelsprüche & Segen',
        'Hochzeit & Jahrestag',
        'Taufe & Geburt',
        'Trauer & Gedenken',
        'Haustiere & Pfotenliebe',
        'Kurze Gravuren (Schmuck)',
        'Saison & Feiertage',
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
        return \App\Support\SafeHtmlAndSvgSanitizer::sanitizeSvg($svg, true);
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
     * Normalisiert einen optionalen Feiertags-Wert auf einen kanonischen Schlüssel aus ProductTemplate::HOLIDAYS.
     */
    public function normalizeQuoteHoliday(?string $holiday): ?string
    {
        if ($holiday === null || trim($holiday) === '') {
            return null;
        }

        $classified = ProductTemplate::classifyHolidaySlot($holiday);
        if ($classified['type'] === 'holiday' && in_array($classified['canonical'], ProductTemplate::HOLIDAYS, true)) {
            return $classified['canonical'];
        }

        $norm = mb_strtolower(trim($holiday));
        foreach (ProductTemplate::HOLIDAYS as $canonical) {
            if (mb_strtolower($canonical) === $norm) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Normalisiert einen optionalen Jahreszeiten-Wert auf einen kanonischen Schlüssel aus ProductTemplate::SEASONS.
     */
    public function normalizeQuoteSeason(?string $season): ?string
    {
        if ($season === null || trim($season) === '') {
            return null;
        }

        $classified = ProductTemplate::classifyHolidaySlot($season);
        if ($classified['type'] === 'season' && in_array($classified['canonical'], ProductTemplate::SEASONS, true)) {
            return $classified['canonical'];
        }

        $norm = mb_strtolower(trim($season));
        foreach (ProductTemplate::SEASONS as $canonical) {
            if (mb_strtolower($canonical) === $norm) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Stellt sicher, dass das Spruch-Manifest unter storage/app/public/produkte/configurator/quotes/manifest.json
     * existiert, alle Standard-Kategorien und kuratierten Seed-Sprüche enthält und 100 % frei von Gedankenstrichen ist.
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
        $needsSave = false;

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

        $legacyDefaultCategories = [
            'Liebe & Partnerschaft',
            'Trauer & Gedenken',
            'Hochzeit & Jahrestag',
            'Taufe & Geburt',
            'Familie & Freundschaft',
            'Haustiere & Pfotenliebe',
            'Kurze Gravuren (Schmuck)',
        ];

        if (empty($manifest['categories']) || $manifest['categories'] === $legacyDefaultCategories) {
            if ($manifest['categories'] !== self::DEFAULT_QUOTE_CATEGORIES) {
                $manifest['categories'] = self::DEFAULT_QUOTE_CATEGORIES;
                $needsSave = true;
            }
        } else {
            foreach (self::DEFAULT_QUOTE_CATEGORIES as $defCat) {
                if (!in_array($defCat, $manifest['categories'], true)) {
                    $manifest['categories'][] = $defCat;
                    $needsSave = true;
                }
            }
        }

        $seedItems = $this->getDefaultSeedQuotes();

        if ($isFirstInit || empty($manifest['items'])) {
            $manifest['items'] = $seedItems;
            $needsSave = true;
        } else {
            // 1. Fehlende Seed-Einträge ergänzen und bestehende Default-Seeds aktualisieren (archived beibehalten)
            foreach ($seedItems as $seedId => $seedData) {
                if (!isset($manifest['items'][$seedId]) || !is_array($manifest['items'][$seedId])) {
                    $manifest['items'][$seedId] = $seedData;
                    $needsSave = true;
                    continue;
                }

                if (($manifest['items'][$seedId]['source'] ?? 'default') === 'default') {
                    $existing = $manifest['items'][$seedId];
                    $existingHoliday = $this->normalizeQuoteHoliday($existing['holiday'] ?? null);
                    $existingSeason = $this->normalizeQuoteSeason($existing['season'] ?? null);

                    if (
                        ($existing['text'] ?? '') !== $seedData['text']
                        || ($existing['title'] ?? '') !== $seedData['title']
                        || ($existing['category'] ?? '') !== $seedData['category']
                        || $existingHoliday !== ($seedData['holiday'] ?? null)
                        || $existingSeason !== ($seedData['season'] ?? null)
                        || !array_key_exists('holiday', $existing)
                        || !array_key_exists('season', $existing)
                    ) {
                        $manifest['items'][$seedId]['title'] = $seedData['title'];
                        $manifest['items'][$seedId]['text'] = $seedData['text'];
                        $manifest['items'][$seedId]['category'] = $seedData['category'];
                        $manifest['items'][$seedId]['holiday'] = $seedData['holiday'] ?? null;
                        $manifest['items'][$seedId]['season'] = $seedData['season'] ?? null;
                        $manifest['items'][$seedId]['archived'] = (bool) ($existing['archived'] ?? false);
                        $needsSave = true;
                    }
                }
            }
        }

        // 2. Harte Null-Gedankenstrich-Garantie & Metadaten-Normalisierung über ALLE Items im Manifest
        foreach ($manifest['items'] as $itemId => &$item) {
            if (!is_array($item)) {
                continue;
            }
            $rawText = (string) ($item['text'] ?? '');
            if ($rawText !== '' && (preg_match('/[\x{2013}\x{2014}\r]/u', $rawText) || preg_match('/\s-\s/u', $rawText))) {
                $sanitized = $this->sanitizeQuoteText($rawText);
                if ($sanitized !== $rawText) {
                    $item['text'] = $sanitized;
                    $needsSave = true;
                }
            }

            $rawTitle = (string) ($item['title'] ?? '');
            if ($rawTitle !== '' && (preg_match('/[\x{2013}\x{2014}]/u', $rawTitle) || preg_match('/\s-\s/u', $rawTitle))) {
                $cleanTitle = preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $rawTitle) ?? $rawTitle;
                $cleanTitle = preg_replace('/\s+-\s+/u', ', ', $cleanTitle) ?? $cleanTitle;
                $cleanTitle = trim($cleanTitle, " \t\n\r\0\x0B,");
                if ($cleanTitle !== $rawTitle) {
                    $item['title'] = $cleanTitle;
                    $needsSave = true;
                }
            }

            $normHoliday = $this->normalizeQuoteHoliday($item['holiday'] ?? null);
            $normSeason = $this->normalizeQuoteSeason($item['season'] ?? null);
            if (!array_key_exists('holiday', $item) || $item['holiday'] !== $normHoliday) {
                $item['holiday'] = $normHoliday;
                $needsSave = true;
            }
            if (!array_key_exists('season', $item) || $item['season'] !== $normSeason) {
                $item['season'] = $normSeason;
                $needsSave = true;
            }
        }
        unset($item);

        if ($needsSave) {
            $this->saveQuotesManifest($manifest);
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
     * Berechnet die saisonale Priorität eines Spruchs zum Referenzzeitpunkt analog zu ProductTemplate:
     * - 10: Aktiver Haupt-Feiertag (bzw. nächster bevorstehender Feiertag im 35-Tage-Vorlauf)
     * - 15: Sekundärer aktiver/bevorstehender Feiertag im Vorlauf
     * - 20: Aktuelle Jahreszeit
     * - 30: Zeitloser Klassiker ohne saisonale Einschränkung
     * - 40: Fremder Feiertag oder fremde Jahreszeit (im Direkt-Vorschlag gesperrt)
     */
    public function resolveQuoteSeasonalPriority(array $quote, ?CarbonInterface $now = null): int
    {
        $holiday = $this->normalizeQuoteHoliday($quote['holiday'] ?? null);
        $season = $this->normalizeQuoteSeason($quote['season'] ?? null);

        return ProductTemplate::resolveHolidaySlotPriority($holiday, $season, $now);
    }

    /**
     * Liefert alle aktiven (nicht archivierten) Sprüche gruppiert nach Kategorie für den Frontend-Konfigurator.
     */
    public function getActiveQuotesGrouped(?CarbonInterface $now = null): array
    {
        $manifest = $this->ensureQuotesInitialized();
        $categories = $manifest['categories'] ?? self::DEFAULT_QUOTE_CATEGORIES;
        $items = $manifest['items'] ?? [];

        $grouped = [];
        foreach ($categories as $cat) {
            $grouped[$cat] = [];
        }

        $orderIndex = 0;
        foreach ($items as $id => $meta) {
            if (!empty($meta['archived'])) {
                continue;
            }
            $text = trim((string) ($meta['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $cat = $meta['category'] ?? ($categories[0] ?? 'Liebe & Partnerschaft');
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }

            $holiday = $this->normalizeQuoteHoliday($meta['holiday'] ?? null);
            $season = $this->normalizeQuoteSeason($meta['season'] ?? null);

            $grouped[$cat][] = [
                'id'        => (string) $id,
                'title'     => (string) ($meta['title'] ?? ''),
                'text'      => $text,
                'category'  => $cat,
                'holiday'   => $holiday,
                'season'    => $season,
                'length'    => mb_strlen($text),
                '_prio'     => ProductTemplate::resolveHolidaySlotPriority($holiday, $season, $now),
                '_ord'      => $orderIndex++,
            ];
        }

        foreach ($grouped as $cat => &$list) {
            if (empty($list)) {
                unset($grouped[$cat]);
                continue;
            }

            if ($cat === 'Saison & Feiertage') {
                usort($list, static fn (array $a, array $b): int => [$a['_prio'], $a['_ord']] <=> [$b['_prio'], $b['_ord']]);
            }

            foreach ($list as &$entry) {
                unset($entry['_prio'], $entry['_ord']);
            }
            unset($entry);
        }
        unset($list);

        return $grouped;
    }

    /**
     * Analysiert ein Produkt anhand von Name, Slug, Konfigurator-Paket und Kategorien auf seinen Themen- & Größenkontext.
     *
     * @return array{is_memorial: bool, is_pet: bool, is_baptism_birth: bool, is_jewelry: bool}
     */
    protected function detectProductQuoteContext(?Product $product): array
    {
        if (!$product) {
            return [
                'is_memorial'      => false,
                'is_pet'           => false,
                'is_baptism_birth' => false,
                'is_jewelry'       => false,
            ];
        }

        $parts = [
            (string) ($product->name ?? ''),
            (string) ($product->slug ?? ''),
        ];

        $cfg = is_array($product->configurator_settings ?? null) ? $product->configurator_settings : [];
        if (!empty($cfg['template_package']) && is_string($cfg['template_package'])) {
            $parts[] = $cfg['template_package'];
        }

        try {
            if ($product->relationLoaded('categories')) {
                foreach ($product->categories as $cat) {
                    $parts[] = (string) ($cat->name ?? '');
                    $parts[] = (string) ($cat->slug ?? '');
                }
            } elseif ($product->exists) {
                foreach ($product->categories()->get(['name', 'slug']) as $cat) {
                    $parts[] = (string) ($cat->name ?? '');
                    $parts[] = (string) ($cat->slug ?? '');
                }
            }
        } catch (\Throwable $e) {
            // Sicherheits-Fallback für unpersistierte Produkt-Instanzen in Tests
        }

        $haystack = mb_strtolower(implode(' ', $parts));

        $isMemorial = (bool) preg_match('/(trauer|gedenk|grab|regenbogenbruecke|regenbogenbrücke|erinnerungslicht|kondolenz|urne|sternenkind)/u', $haystack);
        $isPet = (bool) preg_match('/(pfote|hund|katze|haustier|seelenhund|hundemarke|tierliebe)/u', $haystack);
        $isBaptismBirth = (bool) (
            preg_match('/(taufe|kommunion|konfirmation|taufgeschenk)/u', $haystack)
            || preg_match('/geburt(?!stag)/u', $haystack)
        );
        $isJewelry = (bool) (
            preg_match('/(armband|anhaenger|anhänger|schmuck|kette|collier|talisman|charm|schluessel|schlüssel|hundemarke)/u', $haystack)
            || preg_match('/(^|[\s\-_])(ring|fingerring|ehering|partnerring)($|[\s\-_])/u', $haystack)
        );

        return [
            'is_memorial'      => $isMemorial,
            'is_pet'           => $isPet,
            'is_baptism_birth' => $isBaptismBirth,
            'is_jewelry'       => $isJewelry,
        ];
    }

    /**
     * Ermittelt das kurze, saubere Anlass- oder Saison-Label für das Badge im Konfigurator.
     */
    protected function resolveActiveOccasionLabel(?CarbonInterface $now = null): string
    {
        $ref = $now ? Carbon::instance($now)->copy() : Carbon::now()->copy();
        $ctx = Product::resolveActivePrimarySeasonalSlot($ref);

        if (($ctx['group'] ?? '') === 'holiday' && !empty($ctx['slot'])) {
            return (string) $ctx['slot'];
        }

        foreach (ProductTemplate::HOLIDAYS as $holiday) {
            if (ProductTemplate::resolveHolidaySlotPriority($holiday, null, $ref) === 10) {
                return $holiday;
            }
        }

        if (!empty($ctx['upcoming_holiday_slot'])) {
            $upPrio = ProductTemplate::resolveHolidaySlotPriority((string) $ctx['upcoming_holiday_slot'], null, $ref);
            if ($upPrio <= 15) {
                return (string) $ctx['upcoming_holiday_slot'];
            }
        }

        foreach (ProductTemplate::HOLIDAYS as $holiday) {
            if (ProductTemplate::resolveHolidaySlotPriority($holiday, null, $ref) === 15) {
                return $holiday;
            }
        }

        return (string) ($ctx['season_slot'] ?? 'Winter');
    }

    /**
     * Intelligente 6er-Auswahl-Engine für den Produkt-Konfigurator:
     * Liefert exakt bis zu $limit saison- und produktkontext-passende Direkt-Vorschläge (niemals fremde Feiertage!)
     * sowie das gruppierte Gesamt-Bundle für „Alle Sprüche anzeigen“.
     *
     * @return array{
     *     recommended: array<int, array{id: string, title: string, text: string, category: string, holiday: ?string, season: ?string, length: int}>,
     *     occasion_label: string,
     *     grouped: array<string, array<int, array{id: string, title: string, text: string, category: string, holiday: ?string, season: ?string, length: int}>>,
     *     total_count: int,
     *     hidden_count: int
     * }
     */
    public function getStorefrontQuotesBundle(?Product $product = null, ?CarbonInterface $now = null, int $limit = 6): array
    {
        $ref = $now ? Carbon::instance($now)->copy() : Carbon::now()->copy();
        $limit = max(1, $limit);

        $grouped = $this->getActiveQuotesGrouped($ref);
        $manifest = $this->ensureQuotesInitialized();
        $items = $manifest['items'] ?? [];

        $allActive = [];
        $orderIndex = 0;
        foreach ($items as $id => $meta) {
            if (!is_array($meta) || !empty($meta['archived'])) {
                continue;
            }
            $text = trim((string) ($meta['text'] ?? ''));
            if ($text === '') {
                continue;
            }

            $holiday = $this->normalizeQuoteHoliday($meta['holiday'] ?? null);
            $season = $this->normalizeQuoteSeason($meta['season'] ?? null);

            $allActive[] = [
                'id'       => (string) $id,
                'title'    => (string) ($meta['title'] ?? ''),
                'text'     => $text,
                'category' => (string) ($meta['category'] ?? 'Liebe & Partnerschaft'),
                'holiday'  => $holiday,
                'season'   => $season,
                'length'   => mb_strlen($text),
                '_prio'    => ProductTemplate::resolveHolidaySlotPriority($holiday, $season, $ref),
                '_ord'     => $orderIndex++,
            ];
        }

        $totalCount = count($allActive);
        // Eiserne Regel: Priorität 40 (fremde Feiertage & fremde Jahreszeiten) NIEMALS in den Direkt-Vorschlägen anzeigen!
        $eligible = array_values(array_filter($allActive, static fn (array $q): bool => $q['_prio'] < 40));

        $pCtx = $this->detectProductQuoteContext($product);
        $isJewelry = $pCtx['is_jewelry'];

        $sortByJewelryPreference = static function (array $list) use ($isJewelry): array {
            if (!$isJewelry) {
                usort($list, static fn (array $a, array $b): int => $a['_ord'] <=> $b['_ord']);
                return $list;
            }
            usort($list, static fn (array $a, array $b): int => [
                $a['length'] <= 48 ? 0 : 1,
                $a['_ord'],
            ] <=> [
                $b['length'] <= 48 ? 0 : 1,
                $b['_ord'],
            ]);
            return $list;
        };

        $recommendedRaw = [];

        if ($pCtx['is_memorial']) {
            // 1. Trauer-/Gedenk-Produkt: Keine Party-/Feiertagssprüche, sondern Trauer & Gedenken + tröstende Bibelsprüche
            $occasionLabel = 'Trauer & Trost';

            $memorialQuotes = $sortByJewelryPreference(array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] === 'Trauer & Gedenken'
                    || ($pCtx['is_pet'] && str_contains(mb_strtolower($q['text'] . ' ' . $q['title']), 'regenbogen'))
            )));

            $comfortBibleQuotes = $sortByJewelryPreference(array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] === 'Bibelsprüche & Segen'
                    && $q['holiday'] === null
                    && (bool) preg_match('/(psalm 23|psalm 27|ps\.\s*91|jes\.\s*41|matth\.\s*28|hirte|licht|fürchte|engeln|alle tage)/iu', $q['text'])
            )));

            $pickedIds = [];
            foreach (array_slice($memorialQuotes, 0, min(4, $limit)) as $item) {
                $recommendedRaw[] = $item;
                $pickedIds[$item['id']] = true;
            }
            foreach ($comfortBibleQuotes as $item) {
                if (count($recommendedRaw) >= $limit) {
                    break;
                }
                if (!isset($pickedIds[$item['id']])) {
                    $recommendedRaw[] = $item;
                    $pickedIds[$item['id']] = true;
                }
            }
            foreach (array_merge($memorialQuotes, $comfortBibleQuotes) as $item) {
                if (count($recommendedRaw) >= $limit) {
                    break;
                }
                if (!isset($pickedIds[$item['id']])) {
                    $recommendedRaw[] = $item;
                    $pickedIds[$item['id']] = true;
                }
            }
        } elseif ($pCtx['is_baptism_birth']) {
            // 2. Taufe / Geburt / Kommunion / Konfirmation: Mix aus Taufe & Geburt + kurzen Bibelsprüchen & Segen
            $occasionLabel = 'Taufe & Segen';

            $baptismQuotes = $sortByJewelryPreference(array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] === 'Taufe & Geburt'
            )));

            $blessingBibleQuotes = $sortByJewelryPreference(array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] === 'Bibelsprüche & Segen' && $q['holiday'] === null
            )));

            $pickedIds = [];
            foreach (array_slice($baptismQuotes, 0, min(3, $limit)) as $item) {
                $recommendedRaw[] = $item;
                $pickedIds[$item['id']] = true;
            }
            foreach (array_slice($blessingBibleQuotes, 0, max(0, $limit - count($recommendedRaw))) as $item) {
                if (!isset($pickedIds[$item['id']])) {
                    $recommendedRaw[] = $item;
                    $pickedIds[$item['id']] = true;
                }
            }
            foreach (array_merge($baptismQuotes, $blessingBibleQuotes) as $item) {
                if (count($recommendedRaw) >= $limit) {
                    break;
                }
                if (!isset($pickedIds[$item['id']])) {
                    $recommendedRaw[] = $item;
                    $pickedIds[$item['id']] = true;
                }
            }
        } elseif ($pCtx['is_pet']) {
            // 3. Haustier- / Pfoten-Produkt: Vorrangig Pfotenliebe + passende aktuelle Saison/Kurzgravuren
            $occasionLabel = 'Pfotenliebe';

            $petQuotes = $sortByJewelryPreference(array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] === 'Haustiere & Pfotenliebe'
                    && !str_contains(mb_strtolower($q['text'] . ' ' . $q['title']), 'regenbogen')
            )));

            $seasonalOrShortFillers = array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] !== 'Haustiere & Pfotenliebe'
                    && $q['category'] !== 'Trauer & Gedenken'
            ));
            usort($seasonalOrShortFillers, static fn (array $a, array $b): int => [
                $a['_prio'],
                $isJewelry ? ($a['length'] <= 48 ? 0 : 1) : 0,
                $a['_ord'],
            ] <=> [
                $b['_prio'],
                $isJewelry ? ($b['length'] <= 48 ? 0 : 1) : 0,
                $b['_ord'],
            ]);

            $pickedIds = [];
            foreach (array_slice($petQuotes, 0, min(4, $limit)) as $item) {
                $recommendedRaw[] = $item;
                $pickedIds[$item['id']] = true;
            }
            foreach ($seasonalOrShortFillers as $item) {
                if (count($recommendedRaw) >= $limit) {
                    break;
                }
                if (!isset($pickedIds[$item['id']])) {
                    $recommendedRaw[] = $item;
                    $pickedIds[$item['id']] = true;
                }
            }
            foreach ($petQuotes as $item) {
                if (count($recommendedRaw) >= $limit) {
                    break;
                }
                if (!isset($pickedIds[$item['id']])) {
                    $recommendedRaw[] = $item;
                    $pickedIds[$item['id']] = true;
                }
            }
        } else {
            // 4. Standard Kalender-Steuerung: Prio 10/15 (Feiertag) -> Prio 20 (Jahreszeit) -> Prio 30 (Evergreen)
            $occasionLabel = $this->resolveActiveOccasionLabel($ref);

            $standardCandidates = array_values(array_filter(
                $eligible,
                static fn (array $q): bool => $q['category'] !== 'Trauer & Gedenken'
                    && !str_contains(mb_strtolower($q['text'] . ' ' . $q['title']), 'regenbogenbrücke')
            ));

            $evergreenCategoryRank = $isJewelry
                ? [
                    'Kurze Gravuren (Schmuck)' => 1,
                    'Liebe & Partnerschaft'    => 2,
                    'Bibelsprüche & Segen'     => 3,
                    'Familie & Freundschaft'   => 4,
                    'Hochzeit & Jahrestag'     => 5,
                ]
                : [
                    'Liebe & Partnerschaft'    => 1,
                    'Familie & Freundschaft'   => 2,
                    'Bibelsprüche & Segen'     => 3,
                    'Hochzeit & Jahrestag'     => 4,
                    'Kurze Gravuren (Schmuck)' => 5,
                ];

            usort($standardCandidates, static function (array $a, array $b) use ($isJewelry, $evergreenCategoryRank): int {
                $lenRankA = $isJewelry ? ($a['length'] <= 48 ? 0 : 1) : 0;
                $lenRankB = $isJewelry ? ($b['length'] <= 48 ? 0 : 1) : 0;

                // Innerhalb von Feiertags-Sprüchen (10/15) bevorzugen wir direkte Saison-Widmungen vor Bibelversen auf den vorderen Rängen
                $catRankA = ($a['_prio'] <= 20)
                    ? ($a['category'] === 'Saison & Feiertage' ? 0 : 1)
                    : ($evergreenCategoryRank[$a['category']] ?? 9);
                $catRankB = ($b['_prio'] <= 20)
                    ? ($b['category'] === 'Saison & Feiertage' ? 0 : 1)
                    : ($evergreenCategoryRank[$b['category']] ?? 9);

                return [
                    $a['_prio'],
                    $lenRankA,
                    $catRankA,
                    $a['_ord'],
                ] <=> [
                    $b['_prio'],
                    $lenRankB,
                    $catRankB,
                    $b['_ord'],
                ];
            });

            $recommendedRaw = array_slice($standardCandidates, 0, $limit);
        }

        // Sicherheits-Auffüller, falls ein Spezialkontext weniger als $limit Einträge fand
        if (count($recommendedRaw) < $limit) {
            $pickedIds = array_fill_keys(array_column($recommendedRaw, 'id'), true);
            foreach ($eligible as $fallbackItem) {
                if (count($recommendedRaw) >= $limit) {
                    break;
                }
                if ($pCtx['is_memorial'] && ($fallbackItem['holiday'] !== null || $fallbackItem['category'] === 'Saison & Feiertage')) {
                    continue;
                }
                if (!$pCtx['is_memorial'] && $fallbackItem['category'] === 'Trauer & Gedenken') {
                    continue;
                }
                if (!isset($pickedIds[$fallbackItem['id']])) {
                    $recommendedRaw[] = $fallbackItem;
                    $pickedIds[$fallbackItem['id']] = true;
                }
            }
        }

        $recommended = array_map(static function (array $item): array {
            unset($item['_prio'], $item['_ord']);
            return $item;
        }, array_slice($recommendedRaw, 0, $limit));

        return [
            'recommended'    => $recommended,
            'occasion_label' => $occasionLabel,
            'grouped'        => $grouped,
            'total_count'    => $totalCount,
            'hidden_count'   => max(0, $totalCount - count($recommended)),
        ];
    }

    /**
     * Liefert die komplette Spruch-Datenstruktur für das Admin-Panel (/admin/product-configurator-settings).
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
                'id'         => (string) $id,
                'title'      => trim((string) ($meta['title'] ?? '')),
                'text'       => $text,
                'category'   => $cat,
                'holiday'    => $this->normalizeQuoteHoliday($meta['holiday'] ?? null),
                'season'     => $this->normalizeQuoteSeason($meta['season'] ?? null),
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
    public function addQuote(
        string $text,
        string $category,
        string $title = '',
        string $source = 'manual',
        ?string $holiday = null,
        ?string $season = null
    ): array {
        $manifest = $this->ensureQuotesInitialized();
        $cleanText = $this->sanitizeQuoteText($text);
        if ($cleanText === '') {
            throw new \InvalidArgumentException('Bitte gib einen Spruchtext ein.');
        }

        $cleanCategory = trim($category) !== '' ? trim($category) : ($manifest['categories'][0] ?? 'Liebe & Partnerschaft');
        if (!in_array($cleanCategory, $manifest['categories'], true)) {
            $manifest['categories'][] = $cleanCategory;
        }

        $cleanTitle = trim(strip_tags($title));
        $cleanTitle = preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $cleanTitle) ?? $cleanTitle;
        $cleanTitle = preg_replace('/\s+-\s+/u', ', ', $cleanTitle) ?? $cleanTitle;
        $cleanTitle = trim($cleanTitle, " \t\n\r\0\x0B,");

        $id = 'q_' . substr(md5($cleanCategory . '|' . $cleanText . '|' . microtime(true)), 0, 12);
        $entry = [
            'id'         => $id,
            'title'      => $cleanTitle,
            'text'       => $cleanText,
            'category'   => $cleanCategory,
            'holiday'    => $this->normalizeQuoteHoliday($holiday),
            'season'     => $this->normalizeQuoteSeason($season),
            'archived'   => false,
            'source'     => $source,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        // Vorne einfügen, damit neue Sprüche direkt sichtbar sind
        $manifest['items'] = [$id => $entry] + ($manifest['items'] ?? []);
        $this->saveQuotesManifest($manifest);

        return array_merge($entry, ['length' => mb_strlen($cleanText)]);
    }

    /**
     * Aktualisiert einen bestehenden Spruch (Text, Titel, Kategorie sowie optional Feiertag & Jahreszeit).
     */
    public function updateQuote(
        string $id,
        string $text,
        string $category,
        string $title = '',
        ?string $holiday = null,
        ?string $season = null
    ): bool {
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

        $cleanTitle = trim(strip_tags($title));
        $cleanTitle = preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $cleanTitle) ?? $cleanTitle;
        $cleanTitle = preg_replace('/\s+-\s+/u', ', ', $cleanTitle) ?? $cleanTitle;
        $cleanTitle = trim($cleanTitle, " \t\n\r\0\x0B,");

        $manifest['items'][$id]['text'] = $cleanText;
        $manifest['items'][$id]['title'] = $cleanTitle;
        if ($cleanCategory !== '') {
            $manifest['items'][$id]['category'] = $cleanCategory;
        }

        if (func_num_args() >= 5) {
            $manifest['items'][$id]['holiday'] = $this->normalizeQuoteHoliday($holiday);
        }
        if (func_num_args() >= 6) {
            $manifest['items'][$id]['season'] = $this->normalizeQuoteSeason($season);
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
     * Generiert per Knopfdruck passende, gravurfähige Sprüche (ohne Emojis, ohne Gedankenstriche) über Gemini KI
     * (mit kuratiertem Kreativ-Fallback, falls der API-Key fehlt oder offline ist).
     *
     * @return array<int, array{title: string, text: string, category: string, holiday: ?string, season: ?string, length: int}>
     */
    public function generateAiQuotes(
        string $category,
        string $styleHint = '',
        int $maxLength = 60,
        int $count = 5,
        ?string $holiday = null,
        ?string $season = null
    ): array {
        $category = trim($category) !== '' ? trim($category) : 'Liebe & Partnerschaft';
        $styleHint = trim($styleHint);
        $maxLength = max(20, min(140, $maxLength));
        $count = max(1, min(10, $count));
        $normHoliday = $this->normalizeQuoteHoliday($holiday);
        $normSeason = $this->normalizeQuoteSeason($season);

        $manifest = $this->ensureQuotesInitialized();
        $existingTexts = array_map(
            fn ($i) => mb_strtolower(trim((string) ($i['text'] ?? ''))),
            array_values($manifest['items'] ?? [])
        );

        if (\App\Services\AI\VertexAiService::hasCredentials()) {
            $contextParts = [];
            if ($normHoliday !== null) {
                $contextParts[] = "Feiertags-Anlass: {$normHoliday}.";
            }
            if ($normSeason !== null) {
                $contextParts[] = "Jahreszeit: {$normSeason}.";
            }
            if ($category === 'Bibelsprüche & Segen') {
                $contextParts[] = "Nutze ausschließlich gemeinfreie Bibelworte nach der Lutherbibel 1912 inklusive kurzer Stellenangabe in Klammern wie (Ps. 23,1).";
            }
            if ($styleHint !== '') {
                $contextParts[] = "Zusätzlicher Wunsch / Stil-Hinweis: \"{$styleHint}\".";
            } else {
                $contextParts[] = "Stil: Emotional, edel, zeitlos und perfekt für eine hochwertige Geschenk-Gravur der Manufaktur 'Mein Seelenfunke'.";
            }
            $hintInstruction = implode(' ', $contextParts);

            $prompt = <<<PROMPT
Du bist Chef-Texter für 'Mein Seelenfunke', eine deutsche Manufaktur für personalisierte Lasergravur-Unikate (Kristallglas, Schmuckanhänger, Schiefer, Holz).
Erstelle exakt {$count} verschiedene, tiefgründige und sofort gravierbare Sprüche / Widmungen auf Deutsch für die Kategorie "{$category}".
{$hintInstruction}

WICHTIGE REGELN FÜR DIE LASERGRAVUR:
1. Maximale Zeichenlänge pro Spruch: ca. {$maxLength} Zeichen (kurz, prägnant, ästhetisch auf kleiner Fläche).
2. ZEILENUMBRÜCHE (\n): Füge bei Sprüchen ab 22 Zeichen sinnvolle, harmonische Zeilenumbrüche (\n) nach ca. 18 bis 24 Zeichen pro Zeile ein, damit der Spruch zentriert perfekt auf die Gravurfläche passt und nicht über den Rand hinausragt!
3. ABSOLUTES VERBOT von Gedankenstrichen (\u{2013} oder \u{2014} oder Leerzeichen-Bindestrich-Leerzeichen)! Nutze stattdessen Kommas, Punkte oder Zeilenumbrüche (\n).
4. KEINE Emojis oder Farb-Icons verwenden (ein Laser kann keine Emojis gravieren!). Erlaubt sind normale Satzzeichen, Punkte (•) oder kaufmännisches Und (&).
5. Keine Dubletten zu bestehenden Standardsprüchen.
6. Gib ausschließlich ein valides JSON-Array mit Objekten im Format [{"title": "Kurzer Anlass/Titel (2-3 Wörter)", "text": "Zeile 1\nZeile 2"}] zurück. Kein Markdown, kein Text drumherum.
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
                    $response = \App\Services\AI\VertexAiService::generateContent($model, $payload, 20);

                    if ($response->successful()) {
                        $rawText = $response->json('candidates.0.content.parts.0.text', '[]');
                        $rawText = preg_replace('/^```json\s*|\s*```$/i', '', trim((string) $rawText));
                        $parsed = json_decode((string) $rawText, true);

                        if (is_array($parsed)) {
                            $results = [];
                            foreach ($parsed as $row) {
                                $qText = $this->sanitizeQuoteText((string) ($row['text'] ?? ''));
                                $qTitle = trim(strip_tags((string) ($row['title'] ?? $category)));
                                $qTitle = preg_replace('/\s*[\x{2013}\x{2014}]\s*/u', ', ', $qTitle) ?? $qTitle;
                                $qTitle = preg_replace('/\s+-\s+/u', ', ', $qTitle) ?? $qTitle;
                                $qTitle = trim($qTitle, " \t\n\r\0\x0B,");
                                if ($qText !== '' && !in_array(mb_strtolower($qText), $existingTexts, true)) {
                                    $results[] = [
                                        'title'    => $qTitle,
                                        'text'     => $qText,
                                        'category' => $category,
                                        'holiday'  => $normHoliday,
                                        'season'   => $normSeason,
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
        return $this->getFallbackGeneratedQuotes($category, $styleHint, $maxLength, $count, $existingTexts, $normHoliday, $normSeason);
    }

    /**
     * Bereinigt Spruchtexte für den Laser (keine HTML-Tags, keine Emojis, harte Null-Gedankenstrich-Garantie, saubere Zeilenumbrüche).
     */
    public function sanitizeQuoteText(string $value): string
    {
        $clean = strip_tags($value);
        // Entferne Emojis / Pictographs, lasse deutsche Umlaute, Satzzeichen und Zeilenumbrüche intakt
        $clean = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE00}-\x{FE0F}]/u', '', $clean) ?? $clean;
        $clean = preg_replace("/\r\n|\r/", "\n", $clean) ?? $clean;

        // 1. Gedankenstrich vor Zeilenumbruch -> ",\n" (nach Satzzeichen reines "\n")
        $clean = preg_replace_callback('/([^\n])?[ \t]*[\x{2013}\x{2014}][ \t]*\n/u', static function (array $m): string {
            $prev = $m[1] ?? '';
            if ($prev === '' || preg_match('/[\s\.\!\?\:\,\;\(]/u', $prev)) {
                return rtrim($prev) . "\n";
            }
            return $prev . ",\n";
        }, $clean) ?? $clean;

        // 2. Gedankenstrich am Zeilenanfang oder Textende restlos entfernen
        $clean = preg_replace('/(^|\n)[ \t]*[\x{2013}\x{2014}][ \t]*/u', '$1', $clean) ?? $clean;
        $clean = preg_replace('/[ \t]*[\x{2013}\x{2014}][ \t]*$/u', '', $clean) ?? $clean;

        // 3. Mit Leerzeichen umschlossene Gedankenstriche -> ", " (bzw. " " nach Satzzeichen)
        $clean = preg_replace_callback('/([^\s])[ \t]+(?:[\x{2013}\x{2014}]|-)[ \t]+/u', static function (array $m): string {
            $prev = $m[1];
            if (preg_match('/[\.\!\?\:\,\;]/u', $prev)) {
                return $prev . ' ';
            }
            return $prev . ', ';
        }, $clean) ?? $clean;

        // 4. Direkt an ein Satzzeichen anschließender Gedankenstrich mit Folge-Leerzeichen
        $clean = preg_replace('/([\.\!\?\:\,\;])[\x{2013}\x{2014}][ \t]+/u', '$1 ', $clean) ?? $clean;

        // 5. Verbleibende Gedankenstriche (z. B. zwischen Zahlen) -> kurzer Bindestrich "-"
        $clean = str_replace(["\u{2013}", "\u{2014}"], '-', $clean);

        $clean = preg_replace("/[ \t]+/u", ' ', $clean) ?? $clean;
        $clean = preg_replace("/ *\n */u", "\n", $clean) ?? $clean;
        $clean = trim($clean);

        // Falls ein längerer Spruch noch gar keinen Zeilenumbruch hat, harmonisch umbrechen
        if (!str_contains($clean, "\n") && mb_strlen($clean) > 25) {
            $clean = $this->formatQuoteLineBreaks($clean, 24);
        }

        return $clean;
    }

    /**
     * Bricht zu lange Einzelzeilen harmonisch an Wortgrenzen um (ca. 22 bis 24 Zeichen pro Zeile),
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
     * Kuratierte Initial-Bibliothek für die „Kreativ-Starthilfe“ im Produkt-Konfigurator
     * (100 % gedankenstrichfrei, inkl. gemeinfreier Lutherbibel 1912 & Saison-/Feiertagssteuerung).
     */
    protected function getDefaultSeedQuotes(): array
    {
        $seeds = [
            // 1. Liebe & Partnerschaft
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Seelenverwandt', 'text' => "Du bist mein Lieblingsmensch,\nheute, morgen und für immer."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Heimatgefühl',   'text' => "Wo du bist,\nist mein Zuhause."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Ewige Liebe',    'text' => "Zwei Herzen, ein Takt,\nein gemeinsamer Weg."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Liebesbeweis',   'text' => "Unter Millionen Sternen\nbist du mein hellster Funke."],
            ['category' => 'Liebe & Partnerschaft', 'title' => 'Zusammenhalt',   'text' => "Hand in Hand,\nein Leben lang."],

            // 2. Trauer & Gedenken
            ['category' => 'Trauer & Gedenken', 'title' => 'Unvergessen',       'text' => "Aus dem Leben bist du uns genommen,\naber nicht aus unseren Herzen."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Ewiges Licht',      'text' => "Was man tief im Herzen besitzt,\nkann man durch den Tod\nnicht verlieren."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Sternenhimmel',     'text' => "Wenn du bei Nacht\nden Himmel anschaust,\nlachen dort alle Sterne."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Stille Erinnerung', 'text' => "Für immer geliebt,\nniemals vergessen,\nauf ewig in unseren Herzen."],
            ['category' => 'Trauer & Gedenken', 'title' => 'Spuren der Liebe',  'text' => "Deine Schritte sind verstummt,\ndoch die Spuren deiner Liebe\nbleiben überall."],

            // 3. Hochzeit & Jahrestag
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Ja-Wort',           'text' => "Ein Ja für den Moment,\nein Versprechen für die Ewigkeit."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Eheglück',          'text' => "Aus Du und Ich\nwurde unser schönstes Wir."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Jahrestag',         'text' => "Jeder Tag mit dir\nist mein liebster Tag."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Lebensreise',       'text' => "Liebe heißt, gemeinsam\nin die gleiche Richtung\nzu blicken."],
            ['category' => 'Hochzeit & Jahrestag', 'title' => 'Für das Brautpaar', 'text' => "Möge eure Liebe jeden Tag\naufs Neue funkeln."],

            // 4. Taufe & Geburt
            ['category' => 'Taufe & Geburt', 'title' => 'Willkommen im Leben', 'text' => "Ein kleines Wunder,\ndas unser ganzes Leben\nhell erleuchtet."],
            ['category' => 'Taufe & Geburt', 'title' => 'Schutzengel-Segen',   'text' => "Mögen Engel dich begleiten\nauf all deinen Wegen."],
            ['category' => 'Taufe & Geburt', 'title' => 'Zur Geburt',          'text' => "Die Sterne, die Blumen\nund das Leuchten\nin deinen Kinderaugen."],
            ['category' => 'Taufe & Geburt', 'title' => 'Wurzeln & Flügel',    'text' => "Wurzeln, die dich halten,\nund Flügel, die dich tragen."],
            ['category' => 'Taufe & Geburt', 'title' => 'Taufspruch',          'text' => "Behütet, geliebt und getragen,\nvom ersten Augenblick an."],

            // 5. Familie & Freundschaft
            ['category' => 'Familie & Freundschaft', 'title' => 'Familienbande', 'text' => "Familie ist da, wo das Leben\nbeginnt und die Liebe\nniemals endet."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Beste Mama',    'text' => "Für die Welt bist du eine Mama,\ndoch für uns bist du\ndie ganze Welt."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Bester Papa',   'text' => "Danke, dass du immer\nunser Fels in der Brandung bist."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Wahre Freunde', 'text' => "Gute Freunde sind wie Sterne:\nMan sieht sie nicht immer,\naber sie sind immer da."],
            ['category' => 'Familie & Freundschaft', 'title' => 'Dankbarkeit',   'text' => "Schön, dass es dich gibt,\ndanke für jeden Moment."],

            // 6. Haustiere & Pfotenliebe
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Pfotenabdruck',    'text' => "Kleine Pfoten hinterlassen\ndie größten Spuren\nin unseren Herzen."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Regenbogenbrücke', 'text' => "Bis wir uns hinter der\nRegenbogenbrücke wiedersehen,\nkleiner Freund."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Treue Seele',      'text' => "Treue hat vier Pfoten\nund ein Herz aus Gold."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Seelenhund',       'text' => "Ein Seelenhund bleibt\nfür immer an deiner Seite."],

            // 7. Kurze Gravuren (Schmuck)
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Klassiker',     'text' => "Für immer im Herzen"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Verbundenheit', 'text' => "Für immer & ewig"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Seelenfunke',   'text' => "Mein kleiner\nSeelenfunke"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Glücksbringer', 'text' => "Dein Schutzengel"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Unendlichkeit', 'text' => "Unendlich geliebt"],
            ['category' => 'Kurze Gravuren (Schmuck)', 'title' => 'Erinnerung',    'text' => "Unvergessen"],

            // 8. Bibelsprüche & Segen (Gemeinfreie Lutherbibel 1912, kurz & gravurtauglich)
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Gott ist Liebe',          'text' => "Gott ist die Liebe.\n(1. Joh. 4,16)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Glaube, Hoffnung, Liebe', 'text' => "Nun aber bleibt Glaube,\nHoffnung, Liebe. (1. Kor. 13,13)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Alles in Liebe',          'text' => "Alle eure Dinge lasset\nin Liebe geschehen (1.Kor. 16,14)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Aaronitischer Segen',     'text' => "Der Herr segne dich\nund behüte dich. (4. Mose 6,24)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Mut & Vertrauen',         'text' => "Der Herr, dein Gott,\nist mit dir. (Josua 1,9)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Engelschutz',             'text' => "Er hat seinen Engeln\nbefohlen über dir. (Ps. 91,11)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Guter Hirte',             'text' => "Der Herr ist mein Hirte.\n(Psalm 23,1)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Fürchte dich nicht',      'text' => "Fürchte dich nicht,\nich bin mit dir. (Jes. 41,10)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Licht & Heil',            'text' => "Der Herr ist mein Licht\nund mein Heil. (Psalm 27,1)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Alle Tage bei euch',      'text' => "Ich bin bei euch\nalle Tage. (Matth. 28,20)"],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Friede auf Erden',        'text' => "Und Friede auf Erden!\n(Luk. 2,14)", 'holiday' => 'Weihnachten'],
            ['category' => 'Bibelsprüche & Segen', 'title' => 'Licht der Welt',          'text' => "Ich bin das Licht der Welt.\n(Joh. 8,12)", 'holiday' => 'Ostern'],

            // 9. Kurze Ergänzungen für Trauer & Pfotenliebe (ideal für Schmuck-Gravuren)
            ['category' => 'Trauer & Gedenken',       'title' => 'Herzenskraft',     'text' => "Dem Auge fern,\ndem Herzen ewig nah."],
            ['category' => 'Trauer & Gedenken',       'title' => 'Erinnerungslicht', 'text' => "Ein Licht der Erinnerung,\ndas niemals erlischt."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Pfotenherz',       'text' => "Pfote im Herzen,\nfür alle Zeit."],
            ['category' => 'Haustiere & Pfotenliebe', 'title' => 'Treuer Freund',    'text' => "Mein treuer Begleiter\nauf allen Wegen."],

            // 10. Saison & Feiertage: Weihnachten (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weihnachten', 'title' => 'Weihnachtszauber', 'text' => "Frohe Weihnachten\nund funkelnde Festtage!"],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weihnachten', 'title' => 'Lichterglanz',     'text' => "Ein Licht voll Wärme\nfür deine Weihnachtszeit."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weihnachten', 'title' => 'Herzensfest',      'text' => "Frohes Fest, du bist mein\nschönstes Geschenk."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weihnachten', 'title' => 'Stille Nacht',     'text' => "Besinnliche Stunden,\nWärme und Geborgenheit."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weihnachten', 'title' => 'Weihnachtsfunke',  'text' => "Ein kleiner Seelenfunke\nzum Weihnachtsfest."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weihnachten', 'title' => 'Fest der Liebe',   'text' => "Von Herzen frohe\nund gesegnete Weihnachten."],

            // Saison & Feiertage: Neujahr (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Neujahr', 'title' => 'Glücksjahr',       'text' => "Ein neues Jahr voll Glück,\nMut und Zuversicht."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Neujahr', 'title' => 'Neuanfang',        'text' => "365 neue Tage,\n365 neue Lichtmomente."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Neujahr', 'title' => 'Neujahrssegen',    'text' => "Gesundheit, Liebe und Glück\nfür dein neues Jahr."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Neujahr', 'title' => 'Sternstunden',     'text' => "Möge das neue Jahr für dich\nhell erstrahlen."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Neujahr', 'title' => 'Gemeinsames Jahr', 'text' => "Auf all die schönen Wege,\ndie vor uns liegen."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Neujahr', 'title' => 'Frohes Neues',     'text' => "Frohes neues Jahr\nvoller kleiner Wunder."],

            // Saison & Feiertage: Valentinstag (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Valentinstag', 'title' => 'Mein Valentin', 'text' => "Für meinen Lieblingsmenschen\nzum Valentinstag."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Valentinstag', 'title' => 'Herzschlag',    'text' => "Mein Herz schlägt für dich,\nheute und jeden Tag."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Valentinstag', 'title' => 'Ewiger Funke',  'text' => "Du bist mein schönstes Glück,\nTag für Tag."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Valentinstag', 'title' => 'Seelenliebe',   'text' => "Zwei Herzen, eine Seele,\nfür immer verbunden."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Valentinstag', 'title' => 'Nur mit dir',   'text' => "Jeder Moment mit dir\nist ein Geschenk."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Valentinstag', 'title' => 'Liebesgruß',    'text' => "In Liebe für dich,\nmein Herzensmensch."],

            // Saison & Feiertage: Weltfrauentag (4)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weltfrauentag', 'title' => 'Starke Frau',  'text' => "Für eine wundervolle Frau\nvoll Stärke und Herz."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weltfrauentag', 'title' => 'Einzigartig',  'text' => "Bleib genauso mutig\nund strahlend, wie du bist."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weltfrauentag', 'title' => 'Herzensfrau',  'text' => "Danke für deine Stärke\nund dein großes Herz."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Weltfrauentag', 'title' => 'Frauenpower',  'text' => "Stark, mutig, wunderbar:\nAlles Liebe für dich."],

            // Saison & Feiertage: Ostern (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Ostern', 'title' => 'Osterfreude',   'text' => "Frohe Ostern und sonnige\nFrühlingstage für dich."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Ostern', 'title' => 'Ostersegen',    'text' => "Gesegnete Ostern voll\nLicht und neuer Hoffnung."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Ostern', 'title' => 'Frühlingsgruß', 'text' => "Kleine Osterfreude,\nvon Herzen für dich."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Ostern', 'title' => 'Neues Leben',   'text' => "Frühlingslicht im Herzen\nund frohe Ostertage."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Ostern', 'title' => 'Osterglück',    'text' => "Sonnenschein und Freude\nzum frohen Osterfest."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Ostern', 'title' => 'Herzensgruß',   'text' => "Frohe Ostern für einen\nganz besonderen Menschen."],

            // Saison & Feiertage: Muttertag (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Muttertag', 'title' => 'Beste Mama',    'text' => "Alles Liebe zum Muttertag,\ndu wundervolle Mama."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Muttertag', 'title' => 'Herzensmama',   'text' => "Danke, liebe Mama,\nfür deine unendliche Liebe."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Muttertag', 'title' => 'Mein Anker',    'text' => "Mama, du bist das Herz\nunserer ganzen Familie."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Muttertag', 'title' => 'Für Mama',      'text' => "Für die beste Mama der Welt,\nvon ganzem Herzen."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Muttertag', 'title' => 'Geborgenheit',  'text' => "Bei dir, liebe Mama,\nbin ich immer zuhause."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Muttertag', 'title' => 'Danke Mama',    'text' => "Danke für jedes Lächeln\nund jede Umarmung, Mama."],

            // Saison & Feiertage: Vatertag (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Vatertag', 'title' => 'Bester Papa',          'text' => "Alles Gute zum Vatertag,\nfür den besten Papa."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Vatertag', 'title' => 'Vorbild & Halt',       'text' => "Mein Vorbild, mein Halt:\nDanke für alles, Papa."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Vatertag', 'title' => 'Fels in der Brandung', 'text' => "Danke, Papa, dass du immer\nhinter uns stehst."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Vatertag', 'title' => 'Auf dich, Papa',       'text' => "Auf dich, lieber Papa,\nschön, dass es dich gibt."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Vatertag', 'title' => 'Vaterherz',            'text' => "Starke Hand, großes Herz:\nBester Papa der Welt."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Vatertag', 'title' => 'Danke Papa',           'text' => "Für meinen Papa,\nmit Stolz und Dankbarkeit."],

            // Saison & Feiertage: Einschulung (6)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Einschulung', 'title' => 'Schulkind',         'text' => "Endlich Schulkind!\nViel Freude auf deinem Weg."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Einschulung', 'title' => 'Neues Abenteuer',   'text' => "Mutig und voller Freude\nin die große Schulzeit!"],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Einschulung', 'title' => 'Glücksbringer',     'text' => "Ein kleiner Schutzengel\nfür deine Schulzeit."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Einschulung', 'title' => 'Wissensdurst',      'text' => "Glaube fest an dich,\nkleines großes Schulkind."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Einschulung', 'title' => 'Schulanfang',       'text' => "Alles Liebe und viel Glück\nzum ersten Schultag."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Einschulung', 'title' => 'Kleiner Entdecker', 'text' => "Geh mutig deinen Weg,\nwir sind stolz auf dich."],

            // Saison & Feiertage: Halloween (4)
            ['category' => 'Saison & Feiertage', 'holiday' => 'Halloween', 'title' => 'Herbstzauber', 'text' => "Magische Herbstnacht\nvoll funkelnder Lichter."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Halloween', 'title' => 'Kürbislicht',  'text' => "Ein leuchtender Funke\nin dunkler Oktobernacht."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Halloween', 'title' => 'Nachtzauber',  'text' => "Kleine Magie für\ngemütliche Geisterstunden."],
            ['category' => 'Saison & Feiertage', 'holiday' => 'Halloween', 'title' => 'Oktoberfunke', 'text' => "Heller Lichterschein\nzur magischen Herbstnacht."],

            // Saison & Feiertage: Frühling (6)
            ['category' => 'Saison & Feiertage', 'season' => 'Frühling', 'title' => 'Frühlingslicht',  'text' => "Neue Blüten, neues Glück\nund Sonne im Herzen."],
            ['category' => 'Saison & Feiertage', 'season' => 'Frühling', 'title' => 'Aufblühen',       'text' => "Möge jeder Tag für dich\nwie der Frühling blühen."],
            ['category' => 'Saison & Feiertage', 'season' => 'Frühling', 'title' => 'Sonnenstrahl',    'text' => "Ein warmer Frühlingsgruß,\nvon Herzen für dich."],
            ['category' => 'Saison & Feiertage', 'season' => 'Frühling', 'title' => 'Lebensfreude',    'text' => "Wo Liebe wächst,\nblüht das Leben auf."],
            ['category' => 'Saison & Feiertage', 'season' => 'Frühling', 'title' => 'Morgentau',       'text' => "Frischer Mut und Freude\nfür jeden neuen Tag."],
            ['category' => 'Saison & Feiertage', 'season' => 'Frühling', 'title' => 'Blütenzeit',      'text' => "Kleine Lichtmomente\nfür sonnige Frühlingstage."],

            // Saison & Feiertage: Sommer (6)
            ['category' => 'Saison & Feiertage', 'season' => 'Sommer', 'title' => 'Sommerfunke',    'text' => "Sonne im Herzen und\nfunkelnde Sommermomente."],
            ['category' => 'Saison & Feiertage', 'season' => 'Sommer', 'title' => 'Goldene Zeit',   'text' => "Auf laue Abende und\nunvergessliche Sommertage."],
            ['category' => 'Saison & Feiertage', 'season' => 'Sommer', 'title' => 'Sonnenschein',   'text' => "Du bist mein hellster\nSonnenschein im Leben."],
            ['category' => 'Saison & Feiertage', 'season' => 'Sommer', 'title' => 'Leichtigkeit',   'text' => "Barfuß im Herzen und\nvoller Lebensfreude."],
            ['category' => 'Saison & Feiertage', 'season' => 'Sommer', 'title' => 'Sommerglück',    'text' => "Ein Stück Sommerglück,\nfür immer festgehalten."],
            ['category' => 'Saison & Feiertage', 'season' => 'Sommer', 'title' => 'Lichtsommer',    'text' => "Warme Sonnenstrahlen\nund unbeschwertes Glück."],

            // Saison & Feiertage: Herbst (6)
            ['category' => 'Saison & Feiertage', 'season' => 'Herbst', 'title' => 'Goldener Herbst', 'text' => "Goldenes Laub und warme\nMomente für dein Herz."],
            ['category' => 'Saison & Feiertage', 'season' => 'Herbst', 'title' => 'Herbstwärme',     'text' => "Gemütliche Stunden voll\nWärme und Geborgenheit."],
            ['category' => 'Saison & Feiertage', 'season' => 'Herbst', 'title' => 'Dankbare Ernte',  'text' => "Dankbar für all das Gute,\ndas uns verbindet."],
            ['category' => 'Saison & Feiertage', 'season' => 'Herbst', 'title' => 'Lichtmoment',     'text' => "Ein warmes Licht für\ngoldene Herbsttage."],
            ['category' => 'Saison & Feiertage', 'season' => 'Herbst', 'title' => 'Geborgen',        'text' => "Wenn draußen Stürme ziehen,\nwärmt uns die Liebe."],
            ['category' => 'Saison & Feiertage', 'season' => 'Herbst', 'title' => 'Herbstleuchten',  'text' => "Bunte Blätter, stilles Glück\nund warme Gedanken."],

            // Saison & Feiertage: Winter (6)
            ['category' => 'Saison & Feiertage', 'season' => 'Winter', 'title' => 'Winterzauber', 'text' => "Stille Schneeflocken und\nein Herz voller Wärme."],
            ['category' => 'Saison & Feiertage', 'season' => 'Winter', 'title' => 'Seelenwärme',  'text' => "Ein heller Lichtfunke\nfür kalte Wintertage."],
            ['category' => 'Saison & Feiertage', 'season' => 'Winter', 'title' => 'Winterlicht',  'text' => "Draußen stiller Winter,\ndrinnen Herzenswärme."],
            ['category' => 'Saison & Feiertage', 'season' => 'Winter', 'title' => 'Sternenklar',  'text' => "Unter klarem Winterhimmel\nleuchtet unser Glück."],
            ['category' => 'Saison & Feiertage', 'season' => 'Winter', 'title' => 'Wintergruß',   'text' => "Wärme, Ruhe und Licht\nfür deine Winterzeit."],
            ['category' => 'Saison & Feiertage', 'season' => 'Winter', 'title' => 'Kuschelzeit',  'text' => "Zeit für Ruhe, Nähe\nund funkelnde Momente."],
        ];

        $items = [];
        foreach ($seeds as $idx => $seed) {
            $id = 'seed_' . str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $items[$id] = [
                'id'         => $id,
                'title'      => $seed['title'],
                'text'       => $seed['text'],
                'category'   => $seed['category'],
                'holiday'    => $seed['holiday'] ?? null,
                'season'     => $seed['season'] ?? null,
                'archived'   => false,
                'source'     => 'default',
                'created_at' => '2026-01-01 00:00:00',
            ];
        }

        return $items;
    }

    protected function getFallbackGeneratedQuotes(
        string $category,
        string $styleHint,
        int $maxLength,
        int $count,
        array $existingTexts,
        ?string $holiday = null,
        ?string $season = null
    ): array {
        $pool = [
            'Liebe & Partnerschaft' => [
                ['title' => 'Herzensmensch',   'text' => "Mit dir wird jeder Augenblick\nzu einem Funken Ewigkeit."],
                ['title' => 'Für immer Wir',   'text' => "Du bist mein Anfang,\nmein Glück und mein Zuhause."],
                ['title' => 'Seelenanker',     'text' => "In deinen Armen liegt\nmein schönstes Zuhause."],
                ['title' => 'Liebesfunken',    'text' => "Meine Liebe zu dir wächst\nmit jedem Herzschlag."],
                ['title' => 'Sternstunde',     'text' => "Unter tausend Wegen würde ich\nimmer wieder deinen wählen."],
            ],
            'Trauer & Gedenken' => [
                ['title' => 'Stilles Licht',   'text' => "Ein Seelenfunke erlischt nie,\ner leuchtet im Herzen weiter."],
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
                ['title' => 'Beschützt',       'text' => "Ein Schutzengel an deiner Seite,\nheute und für immer."],
            ],
            'Familie & Freundschaft' => [
                ['title' => 'Herzensband',     'text' => "Zeit vergeht, doch echte\nVerbundenheit bleibt für immer."],
                ['title' => 'Lebensmensch',    'text' => "Danke, dass du mein Leben\nso viel heller machst."],
                ['title' => 'Zusammenhalt',    'text' => "Gemeinsam stark durch jeden\nSturm und jeden Sonnenschein."],
                ['title' => 'Familienherz',    'text' => "Unsere Wurzeln geben uns Halt,\nunsere Liebe gibt uns Kraft."],
                ['title' => 'Freundschaft',    'text' => "Wahre Freundschaft ist ein Schatz,\nder nie seinen Glanz verliert."],
            ],
            'Haustiere & Pfotenliebe' => [
                ['title' => 'Pfotenherz',       'text' => "Vier Pfoten, die mein Herz\nfür immer erobert haben."],
                ['title' => 'Treuer Begleiter', 'text' => "Danke für deine bedingungslose\nTreue an jedem einzelnen Tag."],
                ['title' => 'Sternenpfote',     'text' => "Dein Platz in unserem Herzen\nbleibt für immer dein Zuhause."],
                ['title' => 'Lieblingstier',    'text' => "Du warst nicht nur ein Tier,\ndu warst Familie."],
                ['title' => 'Für immer treu',   'text' => "Spuren im Sand verwehen,\nSpuren im Herzen bleiben."],
            ],
            'Bibelsprüche & Segen' => [
                ['title' => 'Wegweisung',       'text' => "Dein Wort ist meines Fußes\nLeuchte. (Psalm 119,105)"],
                ['title' => 'Starker Schild',   'text' => "Der Herr ist meine Stärke\nund mein Schild. (Psalm 28,7)"],
                ['title' => 'Hoffnungsvoll',    'text' => "Seid fröhlich in Hoffnung,\ngeduldig in Trübsal. (Röm. 12,12)"],
                ['title' => 'Gottes Nähe',      'text' => "Gott ist unsre Zuversicht\nund Stärke. (Psalm 46,2)"],
                ['title' => 'Treuer Hirte',     'text' => "Befiehl dem Herrn deine Wege\nund hoffe auf ihn. (Ps. 37,5)"],
            ],
            'Saison & Feiertage' => [
                ['title' => 'Festmoment',       'text' => "Ein leuchtender Gruß für\nbesondere Festmomente."],
                ['title' => 'Herzenswärme',     'text' => "Wärme, Licht und Freude\nfür diese besondere Zeit."],
                ['title' => 'Sternenglanz',     'text' => "Kleine Augenblicke, die im\nHerzen ewig weiterfunkeln."],
                ['title' => 'Glücksmoment',     'text' => "Von Herzen alles Liebe\nzu diesem schönen Anlass."],
                ['title' => 'Lichtergruß',      'text' => "Ein kleiner Funke Glück\nfür wundervolle Stunden."],
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
            $text = $this->sanitizeQuoteText($cand['text']);
            if ($styleHint !== '' && mb_strlen($text) + mb_strlen($styleHint) + 3 <= $maxLength) {
                $title = ucfirst(mb_substr($styleHint, 0, 24));
            } else {
                $title = $cand['title'];
            }
            if (mb_strlen($text) > $maxLength && $maxLength <= 40) {
                $shortPool = $pool['Kurze Gravuren (Schmuck)'];
                $shortCand = $shortPool[count($results) % count($shortPool)];
                $text = $this->sanitizeQuoteText($shortCand['text']);
                $title = $shortCand['title'];
            }

            if (!in_array(mb_strtolower($text), $existingTexts, true)) {
                $results[] = [
                    'title'    => $title,
                    'text'     => $text,
                    'category' => $category,
                    'holiday'  => $holiday,
                    'season'   => $season,
                    'length'   => mb_strlen($text),
                ];
            }

            if (count($results) >= $count) {
                break;
            }
        }

        if (empty($results)) {
            foreach ($candidates as $cand) {
                $text = $this->sanitizeQuoteText($cand['text']);
                $results[] = [
                    'title'    => $cand['title'],
                    'text'     => $text,
                    'category' => $category,
                    'holiday'  => $holiday,
                    'season'   => $season,
                    'length'   => mb_strlen($text),
                ];
                if (count($results) >= $count) {
                    break;
                }
            }
        }

        return $results;
    }
}
