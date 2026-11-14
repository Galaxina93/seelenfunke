<?php

use App\Models\Ai\AiWorkspaceDocument;
use App\Models\System\SystemLog;
use App\Services\AI\Workspace\DateitrichterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$sanitizeWorkspacePath = function (string $path): ?string {
    $normalized = str_replace('\\', '/', trim($path));
    $normalized = preg_replace('#/+#', '/', $normalized);
    $normalized = trim($normalized, '/');

    if ($normalized === '' || str_contains($normalized, "\0")) {
        return null;
    }

    $segments = explode('/', $normalized);
    foreach ($segments as $segment) {
        if ($segment === '.' || $segment === '..' || $segment === '') {
            return null;
        }
    }

    if ($normalized === 'files') {
        $normalized = 'agenten/workspace';
    } elseif (str_starts_with($normalized, 'files/')) {
        $normalized = 'agenten/workspace/' . substr($normalized, 6);
    }

    if ($normalized !== 'agenten/workspace' && !str_starts_with($normalized, 'agenten/workspace/')) {
        return null;
    }

    $diskRoot = realpath(Storage::disk('workspace')->path('agenten/workspace'));
    $candidatePath = Storage::disk('workspace')->path($normalized);
    if (is_link($candidatePath)) {
        return null;
    }

    $existingReal = realpath($candidatePath);
    if ($diskRoot) {
        if ($existingReal) {
            if ($existingReal !== $diskRoot && !str_starts_with($existingReal, $diskRoot . DIRECTORY_SEPARATOR)) {
                return null;
            }
        } else {
            // Prüfe aufsteigende Elternverzeichnisse gegen Symlink-Escape bei noch nicht existierenden Zieldateien
            $probe = dirname($candidatePath);
            while ($probe && strlen($probe) >= strlen($diskRoot)) {
                if (is_link($probe)) {
                    return null;
                }
                if (file_exists($probe)) {
                    $realProbe = realpath($probe);
                    if (!$realProbe || ($realProbe !== $diskRoot && !str_starts_with($realProbe, $diskRoot . DIRECTORY_SEPARATOR))) {
                        return null;
                    }
                    break;
                }
                $next = dirname($probe);
                if ($next === $probe) {
                    break;
                }
                $probe = $next;
            }
        }
    }

    return $normalized;
};

$formatFileSize = function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1, ',', '.') . ' KB';
    }
    return $bytes . ' B';
};

$getProtectedWorkspacePaths = function (): array {
    return [
        'agenten/workspace',
        'agenten/workspace/Berufsleben',
        'agenten/workspace/Dokumente',
        'agenten/workspace/Gesundheit',
    ];
};

// 1. Dateien & Ordner auflisten (inkl. rekursiver Suche & Dateitrichter-Metadaten)
Route::get('/funki/files', function (Request $request) use ($sanitizeWorkspacePath, $formatFileSize, $getProtectedWorkspacePaths) {
    $disk = Storage::disk('workspace');

    foreach ($getProtectedWorkspacePaths() as $dir) {
        if (!$disk->exists($dir)) {
            $disk->makeDirectory($dir);
        }
    }

    $requestedPath = (string) $request->query('path', 'agenten/workspace');
    $currentPath = $sanitizeWorkspacePath($requestedPath) ?? 'agenten/workspace';
    if (!$disk->exists($currentPath)) {
        $currentPath = 'agenten/workspace';
    }

    $search = trim((string) $request->query('search', ''));
    $isSearch = $search !== '';

    $directories = $isSearch
        ? $disk->allDirectories('agenten/workspace')
        : $disk->directories($currentPath);

    $files = $isSearch
        ? $disk->allFiles('agenten/workspace')
        : $disk->files($currentPath);

    $docs = AiWorkspaceDocument::whereIn('file_path', $files)->get()->keyBy('file_path');
    $matchedSearchPaths = [];
    if ($isSearch) {
        $matchedSearchPaths = AiWorkspaceDocument::search($search)->pluck('file_path')->flip()->all();
    }
    $items = [];

    foreach ($directories as $dir) {
        $name = basename($dir);
        if ($isSearch && stripos($name, $search) === false) {
            continue;
        }
        $modifiedTs = 0;
        try {
            $modifiedTs = $disk->lastModified($dir);
        } catch (\Throwable $e) {
            $modifiedTs = time();
        }
        $items[] = [
            'type' => 'dir',
            'name' => $name,
            'path' => $dir,
            'size' => '-',
            'size_bytes' => 0,
            'extension' => 'Ordner',
            'modified' => date('d.m.Y H:i', $modifiedTs),
            'modified_ts' => $modifiedTs,
            'category' => 'Ordner',
            'tags' => [],
            'purpose' => '',
            'title' => $name,
            'summary' => '',
            'is_protected' => in_array($dir, $getProtectedWorkspacePaths(), true),
        ];
    }

    foreach ($files as $file) {
        $name = basename($file);
        if (str_starts_with($name, '.') || $name === 'funkenwerk_files_db.json') {
            continue;
        }
        $doc = $docs->get($file);

        if ($isSearch) {
            $inDbSearch = isset($matchedSearchPaths[$file]);
            $matchName = stripos($name, $search) !== false;
            $matchTitle = $doc && stripos((string) $doc->title, $search) !== false;
            $matchPurpose = $doc && stripos((string) $doc->purpose, $search) !== false;
            $matchCategory = $doc && stripos((string) $doc->category, $search) !== false;
            if (!$inDbSearch && !$matchName && !$matchTitle && !$matchPurpose && !$matchCategory) {
                continue;
            }
        }

        $sizeBytes = 0;
        $modifiedTs = 0;
        try {
            $sizeBytes = (int) $disk->size($file);
            $modifiedTs = (int) $disk->lastModified($file);
        } catch (\Throwable $e) {
            $modifiedTs = time();
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $tagsList = ($doc && is_array($doc->tags)) ? array_values($doc->tags) : [];
        $items[] = [
            'type' => 'file',
            'name' => $name,
            'path' => $file,
            'size' => $formatFileSize($sizeBytes),
            'size_bytes' => $sizeBytes,
            'extension' => strtoupper($ext ?: 'FILE'),
            'modified' => $doc && $doc->extracted_date
                ? $doc->extracted_date->format('d.m.Y')
                : date('d.m.Y H:i', $modifiedTs),
            'modified_ts' => $modifiedTs,
            'category' => $doc && $doc->category ? $doc->category : 'Unsortiert',
            'tags' => $tagsList,
            'purpose' => $doc && $doc->purpose ? $doc->purpose : '',
            'title' => $doc && $doc->title ? $doc->title : $name,
            'summary' => $doc && $doc->summary ? $doc->summary : '',
            'is_protected' => false,
        ];
    }

    // Alle verfügbaren Zielordner für "Verschieben in..."
    $allDirs = $disk->allDirectories('agenten/workspace');
    $availableFolders = [
        ['path' => 'agenten/workspace', 'label' => 'Hauptverzeichnis (Workspace)'],
    ];
    foreach ($allDirs as $d) {
        $rel = Str::after($d, 'agenten/workspace/');
        $availableFolders[] = [
            'path' => $d,
            'label' => '↳ ' . str_replace('/', ' / ', $rel),
        ];
    }

    return response()->json([
        'success' => true,
        'current_path' => $currentPath,
        'is_root' => $currentPath === 'agenten/workspace',
        'search' => $search,
        'items' => $items,
        'available_folders' => $availableFolders,
    ]);
});

// 2. Neuen Unterordner anlegen (Ein-Wort-Regel & Root-Schutz wie in ManagesAiWorkspaceFiles)
Route::post('/funki/files/folder', function (Request $request) use ($sanitizeWorkspacePath) {
    $currentPath = $sanitizeWorkspacePath((string) $request->input('path', 'agenten/workspace'));
    if (!$currentPath) {
        return response()->json(['success' => false, 'message' => 'Ungültiger Zielpfad.'], 422);
    }

    $rawName = trim((string) $request->input('name', ''));
    $folderName = trim(preg_replace('/[^a-zA-Z0-9äöüÄÖÜß]/u', ' ', $rawName));

    if ($folderName === '') {
        return response()->json(['success' => false, 'message' => 'Bitte einen gültigen Ordnernamen eingeben.'], 422);
    }

    if (preg_match('/[\s_\-]+/u', $folderName) || preg_match('/[\s_\-]+/u', $rawName)) {
        return response()->json([
            'success' => false,
            'message' => 'Ordnernamen dürfen nur aus einem einzigen Wort bestehen (keine Leerzeichen oder Bindestriche).',
        ], 422);
    }

    if ($currentPath === 'agenten/workspace' && !in_array($folderName, ['Berufsleben', 'Dokumente', 'Gesundheit'], true)) {
        return response()->json([
            'success' => false,
            'message' => 'Auf oberster Ebene existieren nur Berufsleben, Dokumente und Gesundheit. Bitte erstelle Unterordner innerhalb dieser 3 Hauptordner.',
        ], 422);
    }

    $newFolderPath = $sanitizeWorkspacePath($currentPath . '/' . $folderName);
    if (!$newFolderPath) {
        return response()->json(['success' => false, 'message' => 'Ungültiger Ordnerpfad.'], 422);
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($newFolderPath)) {
        $disk->makeDirectory($newFolderPath);
    }

    return response()->json([
        'success' => true,
        'path' => $newFolderPath,
        'message' => "Ordner '{$folderName}' wurde erstellt.",
    ]);
});

// 3. Datei(en) hochladen
Route::post('/funki/files/upload', function (Request $request) use ($sanitizeWorkspacePath) {
    $targetPath = $sanitizeWorkspacePath((string) $request->input('path', 'agenten/workspace')) ?? 'agenten/workspace';

    $uploadedFiles = [];
    if ($request->hasFile('files')) {
        $rawFiles = $request->file('files');
        $uploadedFiles = is_array($rawFiles) ? $rawFiles : [$rawFiles];
    } elseif ($request->hasFile('file')) {
        $uploadedFiles = [$request->file('file')];
    }

    if (empty($uploadedFiles)) {
        return response()->json(['success' => false, 'message' => 'Keine Datei zum Hochladen empfangen.'], 422);
    }

    $blockedExtensions = [
        'php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8',
        'sh', 'bash', 'cgi', 'pl', 'py', 'exe', 'bat', 'cmd', 'ps1', 'htaccess', 'jsp', 'asp', 'aspx',
    ];
    $maxUploadBytes = 50 * 1024 * 1024; // 50 MB

    $saved = [];
    foreach ($uploadedFiles as $file) {
        if (!$file || !$file->isValid()) {
            continue;
        }
        if ((int) $file->getSize() > $maxUploadBytes) {
            return response()->json(['success' => false, 'message' => 'Datei überschreitet das Limit von 50 MB.'], 422);
        }
        $filename = basename($file->getClientOriginalName());
        $filename = preg_replace('/[^\w\-. äöüÄÖÜß()]/u', '_', $filename);
        $filename = ltrim((string) $filename, '.');
        if (!$filename || $filename === '.' || $filename === '..') {
            continue;
        }
        $extCheck = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extCheck, $blockedExtensions, true) || preg_match('/\.(php\d*|phtml|phar)\./i', $filename)) {
            return response()->json(['success' => false, 'message' => 'Dieser Dateityp ist aus Sicherheitsgründen nicht erlaubt.'], 422);
        }

        $storedPath = $file->storeAs($targetPath, $filename, 'workspace');
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        AiWorkspaceDocument::updateOrCreate(
            ['file_path' => $storedPath],
            [
                'filename' => $filename,
                'file_type' => $ext,
                'file_size' => $file->getSize(),
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'category' => 'Manueller Upload',
            ]
        );

        SystemLog::create([
            'type' => 'ai',
            'action_id' => 'workspace_upload_funkenwerk',
            'title' => 'Workspace Upload (Funkenwerk): ' . $filename,
            'message' => "Datei '{$filename}' wurde über Funkenwerk in '{$targetPath}' hochgeladen.",
            'status' => 'success',
            'payload' => ['file' => $storedPath, 'size' => $file->getSize()],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $saved[] = $storedPath;
    }

    return response()->json([
        'success' => true,
        'uploaded' => $saved,
        'message' => count($saved) . ' Datei(en) erfolgreich hochgeladen.',
    ]);
});

// 4. KI-Dateitrichter ausführen
Route::post('/funki/files/dateitrichter', function (Request $request) use ($sanitizeWorkspacePath) {
    $currentPath = $sanitizeWorkspacePath((string) $request->input('path', 'agenten/workspace')) ?? 'agenten/workspace';
    $isRoot = ($currentPath === 'agenten/workspace');

    $result = DateitrichterService::processAll(!$isRoot);

    return response()->json([
        'success' => (bool) ($result['success'] ?? false),
        'message' => (string) ($result['message'] ?? 'Dateitrichter abgeschlossen.'),
        'processed_count' => (int) ($result['processed_count'] ?? 0),
        'moved_count' => (int) ($result['moved_count'] ?? 0),
    ]);
});

// 5. Datei oder Ordner umbenennen
Route::post('/funki/files/rename', function (Request $request) use ($sanitizeWorkspacePath, $getProtectedWorkspacePaths) {
    $oldPath = $sanitizeWorkspacePath((string) $request->input('path', ''));
    if (!$oldPath || in_array($oldPath, $getProtectedWorkspacePaths(), true)) {
        return response()->json(['success' => false, 'message' => 'Dieser Hauptordner ist geschützt und kann nicht umbenannt werden.'], 422);
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($oldPath)) {
        return response()->json(['success' => false, 'message' => 'Element wurde nicht gefunden.'], 404);
    }

    $fullOldPath = $disk->path($oldPath);
    $isDirectory = is_dir($fullOldPath);

    $rawNewName = trim((string) $request->input('new_name', ''));
    $cleanNewName = trim(preg_replace('/[^a-zA-Z0-9_\-\. äöüÄÖÜß()]/u', '', $rawNewName));
    if ($cleanNewName === '' || $cleanNewName === '.' || $cleanNewName === '..') {
        return response()->json(['success' => false, 'message' => 'Bitte einen gültigen Namen eingeben.'], 422);
    }

    if ($isDirectory && preg_match('/[\s_\-]+/u', $cleanNewName)) {
        return response()->json(['success' => false, 'message' => 'Ordnernamen dürfen nur aus einem einzigen Wort bestehen.'], 422);
    }

    if (!$isDirectory) {
        $oldExt = pathinfo($oldPath, PATHINFO_EXTENSION);
        $newExt = pathinfo($cleanNewName, PATHINFO_EXTENSION);
        if ($oldExt && !$newExt) {
            $cleanNewName .= '.' . $oldExt;
        }
        if (
            str_starts_with($cleanNewName, '.')
            || preg_match('/\.(php[0-9]?|phtml|phar|sh|bash|exe|bat|cmd|ps1|cgi|pl|py|asp|aspx|jsp|shtml|env|htaccess)(\.|$)/i', $cleanNewName)
        ) {
            return response()->json(['success' => false, 'message' => 'Dieser Dateityp ist aus Sicherheitsgründen nicht erlaubt.'], 422);
        }
    }

    $parentDir = dirname($oldPath);
    $newPath = $sanitizeWorkspacePath($parentDir . '/' . $cleanNewName);
    if (!$newPath || $newPath === $oldPath) {
        return response()->json(['success' => true, 'path' => $oldPath]);
    }

    if ($disk->exists($newPath)) {
        return response()->json(['success' => false, 'message' => 'Ein Element mit diesem Namen existiert bereits.'], 422);
    }

    if ($isDirectory) {
        @rename($fullOldPath, $disk->path($newPath));
        $docs = AiWorkspaceDocument::where('file_path', 'like', $oldPath . '/%')->get();
        foreach ($docs as $doc) {
            $doc->file_path = $newPath . substr($doc->file_path, strlen($oldPath));
            $doc->save();
        }
    } else {
        $disk->move($oldPath, $newPath);
        $doc = AiWorkspaceDocument::where('file_path', $oldPath)->first();
        if ($doc) {
            $doc->file_path = $newPath;
            $doc->filename = basename($newPath);
            $doc->save();
        }
    }

    return response()->json([
        'success' => true,
        'path' => $newPath,
        'message' => 'Erfolgreich umbenannt.',
    ]);
});

// 6. Datei oder Ordner verschieben
Route::post('/funki/files/move', function (Request $request) use ($sanitizeWorkspacePath, $getProtectedWorkspacePaths) {
    $sourcePath = $sanitizeWorkspacePath((string) $request->input('source_path', ''));
    $targetFolder = $sanitizeWorkspacePath((string) $request->input('target_folder', ''));

    if (!$sourcePath || !$targetFolder || in_array($sourcePath, $getProtectedWorkspacePaths(), true)) {
        return response()->json(['success' => false, 'message' => 'Ungültige Verschiebe-Auswahl oder geschützter Hauptordner.'], 422);
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($sourcePath)) {
        return response()->json(['success' => false, 'message' => 'Quell-Element nicht gefunden.'], 404);
    }

    $filename = basename($sourcePath);
    $newPath = $sanitizeWorkspacePath($targetFolder . '/' . $filename);
    if (!$newPath || $newPath === $sourcePath || str_starts_with($newPath, $sourcePath . '/')) {
        return response()->json(['success' => false, 'message' => 'Ungültiger Zielordner.'], 422);
    }

    if ($disk->exists($newPath)) {
        return response()->json(['success' => false, 'message' => 'Im Zielordner existiert bereits ein Element mit diesem Namen.'], 422);
    }

    $fullSource = $disk->path($sourcePath);
    if (is_dir($fullSource)) {
        @rename($fullSource, $disk->path($newPath));
        $docs = AiWorkspaceDocument::where('file_path', 'like', $sourcePath . '/%')->get();
        foreach ($docs as $doc) {
            $doc->file_path = $newPath . substr($doc->file_path, strlen($sourcePath));
            $doc->save();
        }
    } else {
        $disk->move($sourcePath, $newPath);
        $doc = AiWorkspaceDocument::where('file_path', $sourcePath)->first();
        if ($doc) {
            $doc->file_path = $newPath;
            $doc->save();
        }
    }

    return response()->json([
        'success' => true,
        'path' => $newPath,
        'message' => 'Erfolgreich verschoben.',
    ]);
});

// 7. Datei oder Ordner als .zip archivieren
Route::post('/funki/files/archive', function (Request $request) use ($sanitizeWorkspacePath) {
    $safePath = $sanitizeWorkspacePath((string) $request->input('path', ''));
    if (!$safePath || $safePath === 'agenten/workspace') {
        return response()->json(['success' => false, 'message' => 'Ungültiger Pfad zum Archivieren.'], 422);
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($safePath)) {
        return response()->json(['success' => false, 'message' => 'Element nicht gefunden.'], 404);
    }

    $fullPath = $disk->path($safePath);
    if (is_link($fullPath)) {
        return response()->json(['success' => false, 'message' => 'Symlinks können nicht archiviert werden.'], 422);
    }

    $zipPath = $disk->path($safePath . '.zip');
    $zip = new \ZipArchive();
    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
        if (is_dir($fullPath)) {
            $realFullPath = realpath($fullPath) ?: $fullPath;
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($realFullPath, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($files as $file) {
                if ($file->isDir() || $file->isLink()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                if (!$filePath || !str_starts_with($filePath, $realFullPath . DIRECTORY_SEPARATOR)) {
                    continue;
                }
                $relativePath = substr($filePath, strlen($realFullPath) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        } else {
            $zip->addFile($fullPath, basename($fullPath));
        }
        $zip->close();
    } else {
        return response()->json(['success' => false, 'message' => 'ZIP-Archiv konnte nicht erstellt werden.'], 500);
    }

    return response()->json([
        'success' => true,
        'archive_path' => $safePath . '.zip',
        'message' => 'ZIP-Archiv wurde erstellt.',
    ]);
});

// 8. Datei oder Ordner löschen
$deleteWorkspaceItemHandler = function (Request $request) use ($sanitizeWorkspacePath, $getProtectedWorkspacePaths) {
    $rawPath = (string) ($request->input('path') ?? $request->query('path', ''));
    $safePath = $sanitizeWorkspacePath($rawPath);

    if (!$safePath || in_array($safePath, $getProtectedWorkspacePaths(), true)) {
        return response()->json(['success' => false, 'message' => 'Dieser Hauptordner ist geschützt und kann nicht gelöscht werden.'], 422);
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($safePath)) {
        return response()->json(['success' => false, 'message' => 'Element existiert nicht mehr.'], 404);
    }

    $fullPath = $disk->path($safePath);
    if (is_dir($fullPath)) {
        $disk->deleteDirectory($safePath);
        AiWorkspaceDocument::where('file_path', 'like', $safePath . '/%')->delete();
    } else {
        $disk->delete($safePath);
        AiWorkspaceDocument::where('file_path', $safePath)->delete();
    }

    return response()->json([
        'success' => true,
        'message' => 'Erfolgreich gelöscht.',
    ]);
};

Route::delete('/funki/files', $deleteWorkspaceItemHandler);
Route::post('/funki/files/delete', $deleteWorkspaceItemHandler);

// 9. Datei-Vorschau (Text/Metadaten für Lightbox)
Route::get('/funki/files/preview', function (Request $request) use ($sanitizeWorkspacePath, $formatFileSize) {
    $safePath = $sanitizeWorkspacePath((string) $request->query('path', ''));
    if (!$safePath || $safePath === 'agenten/workspace') {
        return response()->json(['success' => false, 'message' => 'Ungültiger Dateipfad.'], 422);
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($safePath) || is_dir($disk->path($safePath))) {
        return response()->json(['success' => false, 'message' => 'Datei nicht gefunden.'], 404);
    }

    $ext = strtolower(pathinfo($safePath, PATHINFO_EXTENSION));
    $sizeBytes = (int) $disk->size($safePath);
    $doc = AiWorkspaceDocument::where('file_path', $safePath)->first();

    $textExtensions = ['txt', 'md', 'json', 'csv', 'log', 'xml', 'yml', 'yaml', 'ini', 'conf', 'html', 'css', 'js', 'ts', 'php', 'py', 'cs', 'sql', 'sh'];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

    $previewKind = 'binary';
    $textContent = null;

    if (in_array($ext, $imageExtensions, true)) {
        $previewKind = 'image';
    } elseif (in_array($ext, $textExtensions, true)) {
        $previewKind = 'text';
        $raw = $disk->get($safePath);
        if (strlen($raw) > 250000) {
            $raw = substr($raw, 0, 250000) . "\n\n... [Vorschau gekürzt bei 250 KB]";
        }
        $textContent = mb_convert_encoding($raw, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    } elseif ($ext === 'pdf') {
        $previewKind = 'pdf';
        if ($doc && ($doc->summary || $doc->purpose)) {
            $textContent = trim(
                ($doc->title ? "Titel: {$doc->title}\n" : '') .
                ($doc->category ? "Kategorie: {$doc->category}\n" : '') .
                ($doc->purpose ? "Zweck: {$doc->purpose}\n\n" : '') .
                ($doc->summary ? "Zusammenfassung:\n{$doc->summary}" : '')
            );
        }
    }

    return response()->json([
        'success' => true,
        'name' => basename($safePath),
        'path' => $safePath,
        'extension' => strtoupper($ext ?: 'FILE'),
        'size' => $formatFileSize($sizeBytes),
        'size_bytes' => $sizeBytes,
        'preview_kind' => $previewKind,
        'text_content' => $textContent,
        'title' => $doc?->title ?? basename($safePath),
        'category' => $doc?->category ?? 'Unsortiert',
        'purpose' => $doc?->purpose ?? '',
        'summary' => $doc?->summary ?? '',
    ]);
});

// 10. Datei herunterladen / Binär-Stream (für Bilder in Lightbox & lokalen Download)
Route::get('/funki/files/download', function (Request $request) use ($sanitizeWorkspacePath) {
    $safePath = $sanitizeWorkspacePath((string) $request->query('path', ''));
    if (!$safePath || $safePath === 'agenten/workspace') {
        abort(422, 'Ungültiger Dateipfad.');
    }

    $disk = Storage::disk('workspace');
    if (!$disk->exists($safePath) || is_dir($disk->path($safePath))) {
        abort(404, 'Datei nicht gefunden.');
    }

    $fullPath = $disk->path($safePath);
    $mime = @mime_content_type($fullPath) ?: 'application/octet-stream';
    $safeInlineMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'text/plain'];
    $disposition = in_array($mime, $safeInlineMimes, true) ? 'inline' : 'attachment';
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($safePath));

    return response()->file($fullPath, [
        'Content-Type' => $mime,
        'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
        'X-Content-Type-Options' => 'nosniff',
    ]);
});

// 11. Verschlüsselte Trichter-Schleuse zum Werkstatt-Mini-PC (00_Eingang_Trichter, Zero-Port-Forwarding)
Route::post('/funki/minipc-trichter/enqueue', function (Request $request) {
    $request->validate([
        'file' => 'required|file|max:25600',
        'custom_title' => 'nullable|string|max:120',
        'source' => 'nullable|string|max:32',
    ]);

    try {
        $meta = \App\Services\System\MiniPcTrichterRelayService::enqueueUploadedFile(
            $request->file('file'),
            $request->input('custom_title'),
            (string) $request->input('source', 'android')
        );

        return response()->json([
            'success' => true,
            'message' => 'Dokument verschlüsselt an den Eingang-Trichter des Werkstatt-Mini-PCs übergeben.',
            'relay_id' => $meta['id'],
            'file_name' => $meta['file_name'],
            'pending_count' => \App\Services\System\MiniPcTrichterRelayService::countPending(),
        ]);
    } catch (\InvalidArgumentException $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'message' => 'Trichter-Übergabe fehlgeschlagen: ' . $e->getMessage(),
        ], 500);
    }
});
