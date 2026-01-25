<?php

namespace App\Livewire\Admin;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class FileManager extends Component
{
    use WithFileUploads, WithPagination;

    public string $currentPath = '';
    public array $folders = [];
    public array $uploads = [];
    public string $search = '';
    public array $selectedFiles = [];
    public bool $selectAll = false;

    public string $selectedUser;
    public array $users = [];

    public string $newFolderName = '';

    public function mount()
    {
        $this->users = $this->loadUsers();
        $this->selectedUser = $this->getAuthUserKey();
        $this->currentPath = $this->getBasePath();
    }

    // NEU: Computed Property, um den Namen des ausgewählten Benutzers zu erhalten
    public function getSelectedUserNameProperty(): string
    {
        // Sucht den Benutzer im $users-Array anhand des Schlüssels
        $user = collect($this->users)->firstWhere('key', $this->selectedUser);
        // Gibt den Namen zurück oder einen Standardwert, falls nicht gefunden
        return $user['name'] ?? 'Unbekannt';
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    private function loadUsers(): array
    {
        $adminUsers = \App\Models\Admin::with('profile')->get()->map(fn($u) => [
            'key' => 'admin:' . $u->id,
            'name' => $u->first_name . ' ' . $u->last_name,
            'type' => 'Admin',
        ]);

        $employeeUsers = \App\Models\Employee::with('profile')->get()->map(fn($u) => [
            'key' => 'employee:' . $u->id,
            'name' => $u->first_name . ' ' . $u->last_name,
            'type' => 'Employee',
        ]);

        $customerUsers = \App\Models\Customer::with('profile')->get()->map(fn($u) => [
            'key' => 'customer:' . $u->id,
            'name' => $u->first_name . ' ' . $u->last_name,
            'type' => 'Customer',
        ]);

        return $adminUsers->merge($employeeUsers)->merge($customerUsers)->sortBy('name')->values()->toArray();
    }

    private function getAuthUserKey(): string
    {
        $user = Auth::user();
        $type = strtolower(class_basename($user));
        return $type . ':' . $user->id;
    }

    public function getBasePath(): string
    {
        [$type, $id] = explode(':', $this->selectedUser);
        $typeUpper = strtoupper($type);
        return "public/user/{$typeUpper}/{$id}/files";
    }

    public function getRelativePath(): string
    {
        return Str::after($this->currentPath, 'files');
    }

    public function selectedUserChanged()
    {
        $this->currentPath = $this->getBasePath();
        $this->resetPage();
        $this->reset('search', 'selectedFiles', 'selectAll');
    }

    public function getFolders(): array
    {
        return collect(Storage::directories($this->currentPath))
            ->map(fn($dir) => basename($dir))
            ->toArray();
    }

    public function getFilteredFiles()
    {
        return collect(Storage::files($this->currentPath))
            ->filter(function ($file) {
                $fileName = basename($file);
                return empty($this->search) || Str::contains(Str::lower($fileName), Str::lower($this->search));
            })
            ->map(function ($file) {
                $name = basename($file);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                return [
                    'name' => $name,
                    'isImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']),
                    'url' => Storage::url($this->currentPath . '/' . $name),
                ];
            })
            ->values();
    }

    public function createNewFolder()
    {
        $this->validate([
            'newFolderName' => ['required', 'string', 'max:255', 'not_regex:/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/'],
        ], [
            'newFolderName.required' => 'Der Ordnername darf nicht leer sein.',
            'newFolderName.not_regex' => 'Der Ordnername enthält ungültige Zeichen.',
        ]);

        $newFolderPath = $this->currentPath . '/' . $this->newFolderName;

        if (Storage::exists($newFolderPath)) {
            $this->addError('newFolderName', 'Ein Ordner mit diesem Namen existiert bereits.');
            return;
        }

        Storage::makeDirectory($newFolderPath);

        $this->reset('newFolderName');
        $this->dispatch('notify-folder-created');
    }

    public function uploadFiles()
    {
        if (empty($this->uploads)) {
            $this->dispatch('notify-uploaded', message: 'Bitte wähle mindestens eine Datei aus.');
            return;
        }

        foreach ($this->uploads as $file) {
            $filename = $file->getClientOriginalName();
            $counter = 1;
            $base = pathinfo($filename, PATHINFO_FILENAME);
            $ext = $file->getClientOriginalExtension();

            while (Storage::exists($this->currentPath . '/' . $filename)) {
                $filename = "{$base}_({$counter}).{$ext}";
                $counter++;
            }

            $file->storeAs($this->currentPath, $filename);
        }

        $this->reset('uploads');
        $this->js("document.querySelector('input[type=\"file\"]').value = '';");
        $this->resetPage();
        $this->dispatch('notify-uploaded');
    }

    public function goToFolder(string $folder)
    {
        $this->currentPath .= '/' . $folder;
        $this->resetPage();
        $this->reset('search', 'selectedFiles', 'selectAll');
    }

    public function goBack()
    {
        $base = $this->getBasePath();
        if ($this->currentPath !== $base) {
            $this->currentPath = dirname($this->currentPath);
            $this->resetPage();
            $this->reset('search', 'selectedFiles', 'selectAll');
        }
    }

    public function deleteFile(string $filename)
    {
        Storage::delete($this->currentPath . '/' . $filename);
        $this->resetPage();
        $this->dispatch('notify-deleted');
    }

    public function deleteFolder(string $folder)
    {
        Storage::deleteDirectory($this->currentPath . '/' . $folder);
        $this->resetPage();
    }

    public function toggleSelectAll()
    {
        if ($this->selectAll) {
            $this->selectedFiles = $this->getFilteredFiles()->pluck('name')->all();
        } else {
            $this->selectedFiles = [];
        }
    }

    public function deleteSelected()
    {
        foreach ($this->selectedFiles as $file) {
            if (is_string($file)) {
                Storage::delete($this->currentPath . '/' . $file);
            }
        }

        $this->selectedFiles = [];
        $this->selectAll = false;

        $this->resetPage();
        $this->dispatch('notify-deleted');
    }

    public function render()
    {
        $files = $this->getFilteredFiles();
        $currentPage = Paginator::resolveCurrentPage() ?: 1;
        $perPage = 6;
        $pagedFiles = $files->forPage($currentPage, $perPage)->values();

        $this->folders = $this->getFolders();

        return view('livewire.admin.file-manager', [
            'paginatedFiles' => new LengthAwarePaginator(
                items: $pagedFiles,
                total: $files->count(),
                perPage: $perPage,
                currentPage: $currentPage,
                options: ['path' => request()->url(), 'query' => request()->query()]
            )
        ]);
    }
}
