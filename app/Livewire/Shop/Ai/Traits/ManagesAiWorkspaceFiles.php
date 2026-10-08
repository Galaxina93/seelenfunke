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

    public function getAllWorkspaceDirectories(): array
    {
        $dirs = Storage::disk('workspace')->allDirectories('agenten/workspace');
        array_unshift($dirs, 'agenten/workspace');
        return $dirs;
    }

    public function loadFileManagerAvailableFolders()
    {
        $this->availableTargetFolders = $this->getAllWorkspaceDirectories();
    }

    public function openFilePreview($path)
    {
        if (Storage::disk('workspace')->exists($path)) {
            $this->previewFilename = basename($path);
            $this->previewContent = Storage::disk('workspace')->get($path);
        }
    }

    public function closeFilePreview()
    {
        $this->previewContent = null;
        $this->previewFilename = null;
    }

    public function loadFileManagerFiles()
    {
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
        $this->currentFilePath .= '/' . trim($folderName, '/');
        $this->loadFileManagerFiles();
    }

    public function goUpFileManagerFolder()
    {
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
        $this->validate([
            'fileUpload' => 'required|file|max:10240' // 10MB max
        ]);

        $filename = $this->fileUpload->getClientOriginalName();
        $this->fileUpload->storeAs($this->currentFilePath, $filename, 'workspace');
        
        $relPath = $this->currentFilePath . '/' . $filename;
        
        // Auto-Registrierung in Datenbank
        AiWorkspaceDocument::updateOrCreate(
            ['file_path' => $relPath],
            [
                'filename' => $filename,
                'file_type' => $this->fileUpload->getClientOriginalExtension(),
                'file_size' => $this->fileUpload->getSize(),
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'category' => 'Manuelle Uploads',
                'purpose' => 'Manuell im Workspace hochgeladenes Arbeitsdokument.',
            ]
        );

        $this->loadFileManagerFiles();
        $this->fileUpload = null;
    }

    public function deleteFileManagerItem($path)
    {
        if (Storage::disk('workspace')->exists($path) || in_array($path, Storage::disk('workspace')->directories(dirname($path)))) {
            if (in_array($path, Storage::disk('workspace')->directories(dirname($path)))) {
                Storage::disk('workspace')->deleteDirectory($path);
                AiWorkspaceDocument::where('file_path', 'like', $path . '/%')->delete();
            } else {
                Storage::disk('workspace')->delete($path);
                AiWorkspaceDocument::where('file_path', $path)->delete();
            }
            $this->loadFileManagerFiles();
        }
    }

    public function renameFileManagerItem($path, $newName)
    {
        $newName = trim($newName);
        if (empty($newName)) return;

        // Wenn es sich um einen Ordner handelt: Regel für einteilige Namen durchsetzen
        if (Storage::disk('workspace')->exists($path) && is_dir(Storage::disk('workspace')->path($path))) {
            if (preg_match('/[\s_\-]+/u', $newName)) {
                $this->dateitrichterMessage = 'Ordnernamen dürfen nur aus einem einzelnen Wort bestehen.';
                return;
            }
        }

        $dir = dirname($path);
        $newPath = $dir . '/' . $newName;

        if ($path !== $newPath && Storage::disk('workspace')->exists($path)) {
            $oldFullPath = Storage::disk('workspace')->path($path);
            $newFullPath = Storage::disk('workspace')->path($newPath);
            
            if (is_dir($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
                // Pfade aller Unterdokumente aktualisieren
                $docs = AiWorkspaceDocument::where('file_path', 'like', $path . '/%')->get();
                foreach ($docs as $doc) {
                    $doc->file_path = $newPath . substr($doc->file_path, strlen($path));
                    $doc->save();
                }
            } else {
                Storage::disk('workspace')->move($path, $newPath);
                $doc = AiWorkspaceDocument::where('file_path', $path)->first();
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
        if (empty($sourcePath) || empty($targetFolder)) return;

        if (str_starts_with($targetFolder, $sourcePath . '/')) return;
        if ($sourcePath === $targetFolder) return;

        $fileName = basename($sourcePath);
        $newPath = $targetFolder . '/' . $fileName;

        if (Storage::disk('workspace')->exists($sourcePath) && !Storage::disk('workspace')->exists($newPath)) {
            $oldFullPath = Storage::disk('workspace')->path($sourcePath);
            $newFullPath = Storage::disk('workspace')->path($newPath);
            
            if (is_dir($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
                $docs = AiWorkspaceDocument::where('file_path', 'like', $sourcePath . '/%')->get();
                foreach ($docs as $doc) {
                    $doc->file_path = $newPath . substr($doc->file_path, strlen($sourcePath));
                    $doc->save();
                }
            } else {
                Storage::disk('workspace')->move($sourcePath, $newPath);
                $doc = AiWorkspaceDocument::where('file_path', $sourcePath)->first();
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
        if (Storage::disk('workspace')->exists($path)) {
            $fullPath = Storage::disk('workspace')->path($path);
            $zipPath = Storage::disk('workspace')->path($path . '.zip');

            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
                if (is_dir($fullPath)) {
                    $files = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($fullPath),
                        \RecursiveIteratorIterator::LEAVES_ONLY
                    );

                    foreach ($files as $name => $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = substr($filePath, strlen($fullPath) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
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
