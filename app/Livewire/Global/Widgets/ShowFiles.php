<?php

namespace App\Livewire\Global\Widgets;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class ShowFiles extends Component
{
    use WithPagination;

    public $user;
    public string $userKey;
    public string $currentPath = '';

    /**
     * Initialisiert die Komponente mit dem übergebenen Benutzer.
     * Leitet den userKey ab und setzt den Startpfad.
     */
    public function mount()
    {
        $this->user = Auth::user();
        $this->userKey = strtolower(class_basename($this->user)) . ':' . $this->user->id;
        $this->currentPath = $this->getBasePath();
    }

    /**
     * Gibt den Basis-Pfad für den aktuellen Benutzer zurück.
     */
    public function getBasePath(): string
    {
        [$type, $id] = explode(':', $this->userKey);
        $typeUpper = strtoupper($type);
        return "public/user/{$typeUpper}/{$id}/files";
    }

    /**
     * Gibt den relativen Pfad zur Anzeige in der UI zurück.
     */
    public function getRelativePath(): string
    {
        // Stellt sicher, dass der Basispfad existiert, bevor wir damit arbeiten
        if (!Str::contains($this->currentPath, 'files')) {
            return '/';
        }
        return Str::after($this->currentPath, 'files');
    }

    /**
     * Ruft die Unterordner im aktuellen Pfad ab.
     */
    public function getFolders(): array
    {
        if (!Storage::exists($this->currentPath)) {
            return [];
        }
        return collect(Storage::directories($this->currentPath))
            ->map(fn($dir) => basename($dir))
            ->toArray();
    }

    /**
     * Ruft die Dateien im aktuellen Pfad ab und formatiert sie für die Anzeige.
     */
    public function getFiles()
    {
        if (!Storage::exists($this->currentPath)) {
            return collect();
        }

        return collect(Storage::files($this->currentPath))
            ->map(function ($file) {
                $name = basename($file);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                return [
                    'name' => $name,
                    'isImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']),
                    'url' => Storage::url($file), // Storage::url benötigt den vollständigen Pfad
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
        $base = $this->getBasePath();
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
            // Optional: Eine Fehlermeldung anzeigen
            $this->dispatch('notify-error', message: 'Datei nicht gefunden.');
            return;
        }

        return Storage::download($filePath);
    }

    /**
     * Rendert die Ansicht mit den paginierten Dateien und Ordnern.
     */
    public function render()
    {
        $files = $this->getFiles();
        $folders = $this->getFolders();

        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 6; // Du kannst die Anzahl pro Seite anpassen
        $pagedFiles = $files->forPage($currentPage, $perPage)->values();

        return view('livewire.widgets.show-files', [
            'folders' => $folders,
            'paginatedFiles' => new LengthAwarePaginator(
                items: $pagedFiles,
                total: $files->count(),
                perPage: $perPage,
                currentPage: $currentPage,
                options: ['path' => request()->url()]
            )
        ]);
    }
}
