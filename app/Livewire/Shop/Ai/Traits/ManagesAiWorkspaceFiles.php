<?php

namespace App\Livewire\Shop\Ai\Traits;

use App\Models\Ai\AiWorkspaceDocument;
use Illuminate\Support\Facades\Storage;

trait ManagesAiWorkspaceFiles
{
    public $fileManagerItems = [];
    public $currentFilePath = 'agenten/workspace';
    public $searchFileManager = '';
    public $newFolderName = '';
    public $fileUpload;
    public $availableTargetFolders = [];

    // Lightbox / File Preview
    public $previewContent = null;
    public $previewFilename = null;

    public ?string $dateitrichterMessage = null;

    protected function ensureWorkspaceAdminAccess(): bool
    {
        return \App\Services\AI\AiAuthHelper::isAdmin();
    }

    protected function getProtectedWorkspacePaths(): array
    {
        return [
            'agenten/workspace',
            'agenten/workspace/Berufsleben',
            'agenten/workspace/Dokumente',
            'agenten/workspace/Gesundheit',
        ];
    }

    protected function sanitizeWorkspacePath(?string $path): ?string
    {
        if (!is_string($path)) {
            return null;
        }
        $clean = trim(str_replace('\\', '/', $path), '/');
        if ($clean === '' || str_contains($clean, '..') || str_contains($clean, "\0")) {
            \App\Services\System\SystemSecurityService::logEvent(
                'security:workspace_path_traversal',
                'Ungültiger Pfad im KI-Workspace blockiert',
                "Pfad-Manipulation im KI-Workspace abgewiesen: " . substr($path, 0, 120),
                'error',
                ['path' => substr($path, 0, 120)]
            );
            return null;
        }
        if ($clean !== 'agenten/workspace' && !str_starts_with($clean, 'agenten/workspace/')) {
            return null;
        }
        return $clean;
    }

    protected function isSafeWorkspaceFilename(string $filename): bool
    {
        if ($filename === '' || str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\') || str_contains($filename, "\0")) {
            return false;
        }
        if (str_starts_with($filename, '.')) {
            return false;
        }
        if (preg_match('/\.(php[0-9]?|phtml|phar|sh|bash|exe|bat|cmd|cgi|pl|py|asp|aspx|jsp|shtml|env|htaccess)(\.|$)/i', $filename)) {
            return false;
        }
        return true;
    }

    public function getAllWorkspaceDirectories(): array
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return [];
        }
        $dirs = Storage::disk('workspace')->allDirectories('agenten/workspace');
        array_unshift($dirs, 'agenten/workspace');
        return $dirs;
    }

    public function loadFileManagerAvailableFolders()
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            $this->availableTargetFolders = [];
            return;
        }
        $this->availableTargetFolders = $this->getAllWorkspaceDirectories();
    }

    public function openFilePreview($path)
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $safePath = $this->sanitizeWorkspacePath((string) $path);
        if (!$safePath) {
            return;
        }
        if (Storage::disk('workspace')->exists($safePath)) {
            $fullPath = Storage::disk('workspace')->path($safePath);
            if (!is_file($fullPath) || is_link($fullPath) || (int) @filesize($fullPath) > 2 * 1024 * 1024) {
                return;
            }
            $this->previewFilename = basename($safePath);
            $this->previewContent = Storage::disk('workspace')->get($safePath);
        }
    }

    public function closeFilePreview()
    {
        $this->previewContent = null;
        $this->previewFilename = null;
    }

    public function loadFileManagerFiles()
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            $this->fileManagerItems = [];
            return;
        }

        // Sicherstellen, dass ausschließlich die 3 Hauptordner existieren
        $defaultFolders = [
            'agenten/workspace',
            'agenten/workspace/Berufsleben',
            'agenten/workspace/Dokumente',
            'agenten/workspace/Gesundheit'
        ];

        foreach ($defaultFolders as $folder) {
            if (!Storage::disk('workspace')->exists($folder)) {
                Storage::disk('workspace')->makeDirectory($folder);
            }
        }

        $this->currentFilePath = $this->sanitizeWorkspacePath((string) $this->currentFilePath) ?? 'agenten/workspace';

        if (!Storage::disk('workspace')->exists($this->currentFilePath)) {
            Storage::disk('workspace')->makeDirectory($this->currentFilePath);
        }

        $items = [];
        $searchQuery = strtolower(trim($this->searchFileManager));

        if (!empty($searchQuery)) {
            // Recursive Search
            $allFiles = Storage::disk('workspace')->allFiles('agenten/workspace');
            $allDirs = Storage::disk('workspace')->allDirectories('agenten/workspace');

            foreach ($allDirs as $dir) {
                if (str_contains(strtolower(basename($dir)), $searchQuery) || str_contains(strtolower($dir), $searchQuery)) {
                    $items[] = [
                        'type' => 'folder',
                        'name' => basename($dir),
                        'path' => $dir,
                        'size' => 0,
                        'lastModified' => Storage::disk('workspace')->lastModified($dir),
                        'mimeType' => 'directory',
                        'url' => null,
                    ];
                }
            }

            foreach ($allFiles as $file) {
                if (str_contains(strtolower(basename($file)), $searchQuery) || str_contains(strtolower($file), $searchQuery)) {
                    $items[] = [
                        'type' => 'file',
                        'name' => basename($file),
                        'path' => $file,
                        'size' => Storage::disk('workspace')->size($file),
                        'lastModified' => Storage::disk('workspace')->lastModified($file),
                        'mimeType' => Storage::disk('workspace')->mimeType($file),
                        'url' => route('admin.ai.workspace.file', ['path' => $file]),
                    ];
                }
            }
        } else {
            // Normal directory listing
            $files = Storage::disk('workspace')->files($this->currentFilePath);
            $dirs = Storage::disk('workspace')->directories($this->currentFilePath);

            foreach($dirs as $dir) {
                $items[] = [
                    'type' => 'folder',
                    'name' => basename($dir),
                    'path' => $dir,
                    'size' => 0,
                    'lastModified' => Storage::disk('workspace')->lastModified($dir),
                    'mimeType' => 'directory',
                    'url' => null,
                ];
            }

            foreach($files as $file) {
                $items[] = [
                    'type' => 'file',
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::disk('workspace')->size($file),
                    'lastModified' => Storage::disk('workspace')->lastModified($file),
                    'mimeType' => Storage::disk('workspace')->mimeType($file),
                    'url' => route('admin.ai.workspace.file', ['path' => $file]),
                ];
            }
        }

        // Batch-Anreicherung mit Datenbank-Dokumenten (Titel, Kategorie, Zweck "Wofür da")
        $filePaths = array_map(fn($f) => $f['path'], array_filter($items, fn($i) => $i['type'] === 'file'));
        if (!empty($filePaths)) {
            $docs = AiWorkspaceDocument::whereIn('file_path', $filePaths)->get()->keyBy('file_path');
            foreach ($items as &$item) {
                if ($item['type'] === 'file') {
                    $doc = $docs->get($item['path']);
                    $item['title'] = $doc?->title ?? $item['name'];
                    $item['category'] = $doc?->category ?? null;
                    $item['purpose'] = $doc?->purpose ?? null;
                    $item['summary'] = $doc?->summary ?? null;
                    $item['date'] = $doc?->extracted_date?->format('d.m.Y') ?? null;
                }
            }
            unset($item);
        }

        $this->fileManagerItems = $items;
    }

    public function updatedSearchFileManager()
    {
        $this->loadFileManagerFiles();
    }

    public function openFileManagerFolder($folderName)
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $cleanFolder = basename(trim((string) $folderName, '/\\'));
        if ($cleanFolder === '' || $cleanFolder === '.' || $cleanFolder === '..') {
            return;
        }
        $candidate = ($this->sanitizeWorkspacePath((string) $this->currentFilePath) ?? 'agenten/workspace') . '/' . $cleanFolder;
        $this->currentFilePath = $this->sanitizeWorkspacePath($candidate) ?? 'agenten/workspace';
        $this->loadFileManagerFiles();
    }

    public function goUpFileManagerFolder()
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        if ($this->currentFilePath !== 'agenten/workspace') {
            $this->currentFilePath = dirname($this->currentFilePath);
            // Fallback safety
            if (!str_starts_with($this->currentFilePath, 'agenten/workspace')) {
                $this->currentFilePath = 'agenten/workspace';
            }
            $this->loadFileManagerFiles();
        }
    }

    public function runDateitrichter()
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        try {
            $service = app(\App\Services\AI\Workspace\DateitrichterService::class);
            // Wenn man im Root ist, sortiere alle Root-Dateien ein. Ansonsten verarbeite neu
            $isRoot = ($this->currentFilePath === 'agenten/workspace');
            $result = $service->processAll(!$isRoot);

            $this->loadFileManagerFiles();
            $this->loadFileManagerAvailableFolders();

            $msg = $result['message'] ?? 'Dateitrichter erfolgreich ausgeführt.';
            $this->dateitrichterMessage = $msg;
            $this->dispatch('dateitrichter-completed', message: $msg);

            // Logge Dateitrichter-Ergebnis in den Chat Log
            if (method_exists($this, 'saveMessageToDb')) {
                $count = $result['processed_count'] ?? 0;
                $icon = $count > 0 ? 'sparkles' : 'funnel';
                $color = $count > 0 ? 'amber-400' : 'gray-400';
                $this->saveMessageToDb('assistant', "> ⚙️ **Dateitrichter:** {$msg}", [
                    'name' => 'Dateitrichter',
                    'color' => $color,
                    'icon' => $icon,
                    'profile_picture' => null,
                ]);
                unset($this->messages);
                $this->dispatch('chat-memory-updated');
            }

            if (class_exists(\App\Models\System\SystemLog::class)) {
                \App\Models\System\SystemLog::create([
                    'type' => 'workspace_dateitrichter',
                    'title' => 'Dateitrichter ausgeführt',
                    'message' => $msg,
                    'status' => 'success',
                    'payload' => $result,
                    'started_at' => now(),
                    'finished_at' => now(),
                ]);
            }
        } catch (\Exception $e) {
            $this->dateitrichterMessage = 'Fehler im Dateitrichter: ' . $e->getMessage();
            $this->dispatch('dateitrichter-completed', message: $this->dateitrichterMessage);
        }
    }

    public function clearDateitrichterMessage()
    {
        $this->dateitrichterMessage = null;
    }

    public function createFileManagerFolder()
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $this->validate([
            'newFolderName' => 'required|string|max:255'
        ]);

        $folderName = trim($this->newFolderName);

        // Regel: Keine Ordner mit mehr als einem Namen (nur ein einzelnes Wort, keine Leerzeichen, keine Unterstriche)
        if (preg_match('/[\s_\-]+/u', $folderName)) {
            $this->dateitrichterMessage = 'Ordnernamen dürfen nur aus einem einzelnen Wort bestehen (z.B. Projekte, Finanzen, Nachweise).';
            return;
        }

        // Regel: Auf Root-Ebene dürfen ausschließlich die 3 Hauptordner existieren
        if ($this->currentFilePath === 'agenten/workspace') {
            if (!in_array($folderName, ['Berufsleben', 'Dokumente', 'Gesundheit'])) {
                $this->dateitrichterMessage = 'Auf der obersten Ebene sind ausschließlich die 3 Hauptordner "Berufsleben", "Dokumente" und "Gesundheit" zulässig.';
                return;
            }
        }

        $path = $this->currentFilePath . '/' . $folderName;
        if (!Storage::disk('workspace')->exists($path)) {
            Storage::disk('workspace')->makeDirectory($path);
            $this->loadFileManagerFiles();
            $this->loadFileManagerAvailableFolders();
            $this->newFolderName = '';
            $this->dateitrichterMessage = "Ordner '{$folderName}' erfolgreich angelegt.";
        }
    }

    public function updatedFileUpload()
    {
        $this->uploadFileManagerFile();
    }

    public function uploadFileManagerFile()
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            $this->fileUpload = null;
            return;
        }
        $this->validate([
            'fileUpload' => 'required|file|max:10240' // 10MB max
        ]);

        $this->currentFilePath = $this->sanitizeWorkspacePath((string) $this->currentFilePath) ?? 'agenten/workspace';
        $filename = basename((string) $this->fileUpload->getClientOriginalName());

        if (!$this->isSafeWorkspaceFilename($filename)) {
            $this->dateitrichterMessage = 'Ungültiger oder aus Sicherheitsgründen blockierter Dateiname.';
            $this->fileUpload = null;
            return;
        }

        $fileSize = $this->fileUpload->getSize();
        $sizeFormatted = round($fileSize / 1024, 1) . ' KB';
        $this->fileUpload->storeAs($this->currentFilePath, $filename, 'workspace');
        
        $relPath = $this->currentFilePath . '/' . $filename;
        
        // Auto-Registrierung in Datenbank
        AiWorkspaceDocument::updateOrCreate(
            ['file_path' => $relPath],
            [
                'filename' => $filename,
                'file_type' => $this->fileUpload->getClientOriginalExtension(),
                'file_size' => $fileSize,
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'category' => 'Manuelle Uploads',
                'purpose' => 'Manuell im Workspace hochgeladenes Arbeitsdokument.',
            ]
        );

        // 1. In den Chat Log (AiChatMemory) schreiben, damit der Upload sichtbar ist
        if (method_exists($this, 'saveMessageToDb')) {
            $this->saveMessageToDb('assistant', "> 📥 **Datei hochgeladen:** `{$filename}` ({$sizeFormatted}) im Workspace-Ordner `{$this->currentFilePath}` abgelegt.", [
                'name' => 'Dateisystem',
                'color' => 'blue-400',
                'icon' => 'arrow-up-tray',
                'profile_picture' => null,
            ]);
            unset($this->messages);
            $this->dispatch('chat-memory-updated');
        }

        // 2. In SystemLog eintragen
        if (class_exists(\App\Models\System\SystemLog::class)) {
            \App\Models\System\SystemLog::create([
                'type' => 'workspace_upload',
                'title' => 'Datei hochgeladen: ' . $filename,
                'message' => "Datei '{$filename}' ({$sizeFormatted}) wurde im Workspace unter '{$relPath}' hochgeladen.",
                'status' => 'success',
                'payload' => [
                    'filename' => $filename,
                    'path' => $relPath,
                    'size' => $fileSize,
                    'uploaded_at' => now()->toIso8601String(),
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        // 3. Ephemeral State der Session für sofortigen KI-Kontext aktualisieren
        session()->put('ai_ephemeral_state', "Zuletzt hochgeladene Datei im Workspace: '{$filename}' (Pfad: '{$relPath}', Zeit: " . now()->format('d.m.Y H:i:s') . ").");

        $this->dateitrichterMessage = "Datei '{$filename}' ({$sizeFormatted}) erfolgreich hochgeladen.";

        $this->loadFileManagerFiles();
        $this->fileUpload = null;
    }

    public function deleteFileManagerItem($path)
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $safePath = $this->sanitizeWorkspacePath((string) $path);
        $protectedPaths = $this->getProtectedWorkspacePaths();
        if (!$safePath || in_array($safePath, $protectedPaths, true)) {
            return;
        }

        if (Storage::disk('workspace')->exists($safePath) || in_array($safePath, Storage::disk('workspace')->directories(dirname($safePath)))) {
            if (in_array($safePath, Storage::disk('workspace')->directories(dirname($safePath)))) {
                Storage::disk('workspace')->deleteDirectory($safePath);
                AiWorkspaceDocument::where('file_path', 'like', $safePath . '/%')->delete();
            } else {
                Storage::disk('workspace')->delete($safePath);
                AiWorkspaceDocument::where('file_path', $safePath)->delete();
            }
            $this->loadFileManagerFiles();
        }
    }

    public function renameFileManagerItem($path, $newName)
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $safePath = $this->sanitizeWorkspacePath((string) $path);
        $newName = basename(trim((string) $newName));
        if (!$safePath || in_array($safePath, $this->getProtectedWorkspacePaths(), true) || empty($newName)) return;

        $oldFullPath = Storage::disk('workspace')->path($safePath);
        if (is_link($oldFullPath)) {
            return;
        }

        // Wenn es sich um einen Ordner handelt: Regel für einteilige Namen durchsetzen
        if (Storage::disk('workspace')->exists($safePath) && is_dir($oldFullPath)) {
            if (dirname($safePath) === 'agenten/workspace') {
                return;
            }
            if (preg_match('/[\s_\-]+/u', $newName)) {
                $this->dateitrichterMessage = 'Ordnernamen dürfen nur aus einem einzelnen Wort bestehen.';
                return;
            }
        } elseif (!$this->isSafeWorkspaceFilename($newName)) {
            $this->dateitrichterMessage = 'Ungültiger oder blockierter Dateiname.';
            return;
        }

        $dir = dirname($safePath);
        $newPath = $this->sanitizeWorkspacePath($dir . '/' . $newName);
        if (!$newPath) return;

        if ($safePath !== $newPath && Storage::disk('workspace')->exists($safePath)) {
            $newFullPath = Storage::disk('workspace')->path($newPath);
            
            if (is_dir($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
                // Pfade aller Unterdokumente aktualisieren
                $docs = AiWorkspaceDocument::where('file_path', 'like', $safePath . '/%')->get();
                foreach ($docs as $doc) {
                    $doc->file_path = $newPath . substr($doc->file_path, strlen($safePath));
                    $doc->save();
                }
            } else {
                Storage::disk('workspace')->move($safePath, $newPath);
                $doc = AiWorkspaceDocument::where('file_path', $safePath)->first();
                if ($doc) {
                    $doc->file_path = $newPath;
                    $doc->filename = $newName;
                    $doc->save();
                }
            }
            $this->loadFileManagerFiles();
        }
    }

    public function moveFileManagerItem($sourcePath, $targetFolder)
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $safeSource = $this->sanitizeWorkspacePath((string) $sourcePath);
        $safeTarget = $this->sanitizeWorkspacePath((string) $targetFolder);
        if (!$safeSource || !$safeTarget || in_array($safeSource, $this->getProtectedWorkspacePaths(), true) || $safeTarget === 'agenten/workspace') return;

        if (str_starts_with($safeTarget, $safeSource . '/')) return;
        if ($safeSource === $safeTarget) return;

        $fileName = basename($safeSource);
        $newPath = $this->sanitizeWorkspacePath($safeTarget . '/' . $fileName);
        if (!$newPath) return;

        if (Storage::disk('workspace')->exists($safeSource) && !Storage::disk('workspace')->exists($newPath)) {
            $oldFullPath = Storage::disk('workspace')->path($safeSource);
            if (is_link($oldFullPath)) {
                return;
            }
            $newFullPath = Storage::disk('workspace')->path($newPath);
            
            if (is_dir($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
                $docs = AiWorkspaceDocument::where('file_path', 'like', $safeSource . '/%')->get();
                foreach ($docs as $doc) {
                    $doc->file_path = $newPath . substr($doc->file_path, strlen($safeSource));
                    $doc->save();
                }
            } else {
                Storage::disk('workspace')->move($safeSource, $newPath);
                $doc = AiWorkspaceDocument::where('file_path', $safeSource)->first();
                if ($doc) {
                    $doc->file_path = $newPath;
                    $doc->save();
                }
            }
            $this->loadFileManagerFiles();
        }
    }

    public function archiveFileManagerItem($path)
    {
        if (!$this->ensureWorkspaceAdminAccess()) {
            return;
        }
        $safePath = $this->sanitizeWorkspacePath((string) $path);
        if (!$safePath || $safePath === 'agenten/workspace') return;

        if (Storage::disk('workspace')->exists($safePath)) {
            $fullPath = Storage::disk('workspace')->path($safePath);
            if (is_link($fullPath)) {
                return;
            }
            $zipPath = Storage::disk('workspace')->path($safePath . '.zip');

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                if (is_dir($fullPath)) {
                    $realFullPath = realpath($fullPath) ?: $fullPath;
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($realFullPath, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    foreach ($files as $name => $file) {
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
                $this->loadFileManagerFiles();
            }
        }
    }
}
