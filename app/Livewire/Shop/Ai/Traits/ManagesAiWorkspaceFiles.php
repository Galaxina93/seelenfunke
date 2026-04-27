<?php

namespace App\Livewire\Shop\Ai\Traits;

use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;

trait ManagesAiWorkspaceFiles
{
    public $currentFilePath = 'agenten/workspace';
    public $fileManagerItems = [];
    public $newFolderName = '';
    public $searchFileManager = '';
    public $fileUpload;
    public $previewContent = null;
    public $previewFilename = null;

    #[Computed]
    public function getAllWorkspaceDirectories()
    {
        $dirs = Storage::disk('public')->allDirectories('agenten/workspace');
        array_unshift($dirs, 'agenten/workspace');
        return $dirs;
    }

    public function openFilePreview($path)
    {
        if (Storage::disk('public')->exists($path)) {
            $this->previewFilename = basename($path);
            $this->previewContent = Storage::disk('public')->get($path);
        }
    }

    public function closeFilePreview()
    {
        $this->previewContent = null;
        $this->previewFilename = null;
    }

    public function loadFileManagerFiles()
    {
        // Ensure root structure exists
        $requiredDirs = [
            'agenten/workspace',
        ];

        foreach ($requiredDirs as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                Storage::disk('public')->makeDirectory($dir);
            }
        }

        if (!Storage::disk('public')->exists($this->currentFilePath)) {
            Storage::disk('public')->makeDirectory($this->currentFilePath);
        }

        $items = [];
        $searchQuery = strtolower(trim($this->searchFileManager));

        if (!empty($searchQuery)) {
            // Recursive Search
            $allFiles = Storage::disk('public')->allFiles('agenten/workspace');
            $allDirs = Storage::disk('public')->allDirectories('agenten/workspace');

            foreach ($allDirs as $dir) {
                if (str_contains(strtolower(basename($dir)), $searchQuery) || str_contains(strtolower($dir), $searchQuery)) {
                    $items[] = [
                        'type' => 'folder',
                        'name' => basename($dir),
                        'path' => $dir,
                        'size' => 0,
                        'lastModified' => Storage::disk('public')->lastModified($dir),
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
                        'size' => Storage::disk('public')->size($file),
                        'lastModified' => Storage::disk('public')->lastModified($file),
                        'mimeType' => Storage::disk('public')->mimeType($file),
                        'url' => Storage::url($file),
                    ];
                }
            }
        } else {
            // Normal directory listing
            $files = Storage::disk('public')->files($this->currentFilePath);
            $dirs = Storage::disk('public')->directories($this->currentFilePath);

            foreach($dirs as $dir) {
                $items[] = [
                    'type' => 'folder',
                    'name' => basename($dir),
                    'path' => $dir,
                    'size' => 0,
                    'lastModified' => Storage::disk('public')->lastModified($dir),
                    'mimeType' => 'directory',
                    'url' => null,
                ];
            }

            foreach($files as $file) {
                $items[] = [
                    'type' => 'file',
                    'name' => basename($file),
                    'path' => $file,
                    'size' => Storage::disk('public')->size($file),
                    'lastModified' => Storage::disk('public')->lastModified($file),
                    'mimeType' => Storage::disk('public')->mimeType($file),
                    'url' => Storage::url($file),
                ];
            }
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

    public function createFileManagerFolder()
    {
        $this->validate([
            'newFolderName' => 'required|string|max:255'
        ]);

        $path = $this->currentFilePath . '/' . trim($this->newFolderName);
        if (!Storage::disk('public')->exists($path)) {
            Storage::disk('public')->makeDirectory($path);
            $this->loadFileManagerFiles();
            $this->newFolderName = '';
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

        $this->fileUpload->storeAs($this->currentFilePath, $this->fileUpload->getClientOriginalName(), 'public');
        $this->loadFileManagerFiles();
        $this->fileUpload = null;
    }

    public function deleteFileManagerItem($path)
    {
        if (Storage::disk('public')->exists($path) || in_array($path, Storage::disk('public')->directories(dirname($path)))) {
            if (in_array($path, Storage::disk('public')->directories(dirname($path)))) {
                Storage::disk('public')->deleteDirectory($path);
            } else {
                Storage::disk('public')->delete($path);
            }
            $this->loadFileManagerFiles();
        }
    }

    public function renameFileManagerItem($path, $newName)
    {
        $newName = trim($newName);
        if (empty($newName)) return;

        $dir = dirname($path);
        // If path is at root, dirname might be '.' or 'agenten/workspace', ensure we construct it properly.
        $newPath = $dir . '/' . $newName;

        if ($path !== $newPath && Storage::disk('public')->exists($path)) {
            $oldFullPath = Storage::disk('public')->path($path);
            $newFullPath = Storage::disk('public')->path($newPath);
            
            if (is_dir($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
            } else {
                Storage::disk('public')->move($path, $newPath);
            }
            $this->loadFileManagerFiles();
        }
    }

    public function moveFileManagerItem($sourcePath, $targetFolder)
    {
        if (empty($sourcePath) || empty($targetFolder)) return;

        // Prevent moving a folder into itself
        if (str_starts_with($targetFolder, $sourcePath . '/')) return;
        if ($sourcePath === $targetFolder) return;

        $fileName = basename($sourcePath);
        $newPath = $targetFolder . '/' . $fileName;

        if (Storage::disk('public')->exists($sourcePath) && !Storage::disk('public')->exists($newPath)) {
            $oldFullPath = Storage::disk('public')->path($sourcePath);
            $newFullPath = Storage::disk('public')->path($newPath);
            
            if (is_dir($oldFullPath)) {
                rename($oldFullPath, $newFullPath);
            } else {
                Storage::disk('public')->move($sourcePath, $newPath);
            }
            $this->loadFileManagerFiles();
        }
    }

    public function archiveFileManagerItem($path)
    {
        if (Storage::disk('public')->exists($path)) {
            $fullPath = Storage::disk('public')->path($path);
            $zipPath = Storage::disk('public')->path($path . '.zip');

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
