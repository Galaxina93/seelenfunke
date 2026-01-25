<?php

namespace App\Livewire\Global\Widgets;

use App\Models\Directory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ShowDirectories extends Component
{
    use WithPagination;

    public $user;
    public $sharedDirectories;

    public ?Directory $selectedDirectory = null;
    public string $currentPath = '';

    /**
     * Lädt die für den Benutzer freigegebenen Verzeichnisse.
     */
    public function mount()
    {
        $this->user = Auth::user();
        $this->user->load('directories'); // Eager loading
        $this->sharedDirectories = $this->user->directories;
    }

    /**
     * Wählt ein freigegebenes Verzeichnis aus und zeigt dessen Inhalt an.
     */
    public function selectDirectory(int $directoryId)
    {
        $this->selectedDirectory = $this->sharedDirectories->find($directoryId);
        if ($this->selectedDirectory) {
            $this->currentPath = $this->selectedDirectory->path;
            $this->resetPage();
        }
    }

    /**
     * Kehrt zur Übersicht der freigegebenen Verzeichnisse zurück.
     */
    public function unselectDirectory()
    {
        $this->selectedDirectory = null;
        $this->currentPath = '';
        $this->resetPage();
    }

    /**
     * Gibt den relativen Pfad zur Anzeige in der UI zurück.
     */
    public function getRelativePath(): string
    {
        if (!$this->selectedDirectory) {
            return '';
        }
        $basePath = $this->selectedDirectory->path;
        if ($this->currentPath === $basePath) {
            return '/';
        }
        // Str::after gibt alles nach dem ersten Vorkommen zurück.
        return Str::after($this->currentPath, $basePath);
    }

    /**
     * Ruft die Unterordner im aktuellen Pfad ab.
     */
    public function getFolders(): array
    {
        if (!$this->currentPath || !Storage::exists($this->currentPath)) {
            return [];
        }
        return collect(Storage::directories($this->currentPath))
            ->map(fn($dir) => basename($dir))
            ->toArray();
    }

    /**
     * Ruft die Dateien im aktuellen Pfad ab.
     */
    public function getFiles()
    {
        if (!$this->currentPath || !Storage::exists($this->currentPath)) {
            return collect();
        }

        return collect(Storage::files($this->currentPath))
            ->map(function ($file) {
                $name = basename($file);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                return [
                    'name' => $name,
                    'isImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']),
                    'url' => Storage::url($file),
                ];
            })
            ->values();
    }

    /**
     * Navigiert in einen Unterordner.
     */
    public function goToFolder(string $folder)
    {
        $this->currentPath .= '/' . $folder;
        $this->resetPage();
    }

    /**
     * Navigiert eine Ordnerebene zurück.
     */
    public function goBack()
    {
        $base = $this->selectedDirectory->path;
        if ($this->currentPath !== $base) {
            $this->currentPath = dirname($this->currentPath);
            $this->resetPage();
        }
    }

    /**
     * Löst den Download einer Datei aus.
     */
    public function downloadFile(string $filename)
    {
        $filePath = $this->currentPath . '/' . $filename;

        if (!Storage::exists($filePath)) {
            $this->dispatch('notify-error', message: 'Datei nicht gefunden.');
            return null;
        }

        return Storage::download($filePath);
    }

    /**
     * Rendert die Ansicht.
     */
    public function render()
    {
        $files = collect();
        $folders = [];

        if ($this->selectedDirectory) {
            $files = $this->getFiles();
            $folders = $this->getFolders();
        }

        $currentPage = Paginator::resolveCurrentPage('page') ?: 1;
        $perPage = 12;
        $pagedFiles = $files->forPage($currentPage, $perPage)->values();

        return view('livewire.widgets.show-directories', [
            'folders' => $folders,
            'paginatedFiles' => new LengthAwarePaginator(
                items: $pagedFiles,
                total: $files->count(),
                perPage: $perPage,
                currentPage: $currentPage,
                options: ['path' => request()->url(), 'pageName' => 'page']
            )
        ]);
    }
}
