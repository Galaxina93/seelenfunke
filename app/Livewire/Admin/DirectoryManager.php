<?php

namespace App\Livewire\Admin;

use App\Models\Directory;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class DirectoryManager extends Component
{
    use WithFileUploads, WithPagination;

    public string $newDirectoryName = '';
    public ?int $editingDirectoryId = null;
    public string $editingDirectoryName = '';

    public ?Directory $selectedDirectory = null;
    public array $users = [];
    public array $assignedUsers = [];

    public array $uploads = [];
    public string $search = ''; // Dieser Wert wird jetzt für die Verzeichnissuche verwendet

    protected function rules(): array
    {
        return [
            'newDirectoryName' => ['required', 'string', 'max:50', 'not_regex:/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', 'unique:directories,name'],
            'editingDirectoryName' => ['required', 'string', 'max:50', 'not_regex:/[\\\\\\/\\:\\*\\?\\"\\<\\>\\|]/', 'unique:directories,name,' . $this->editingDirectoryId],
            'uploads.*' => 'max:10240', // 10MB Max
        ];
    }

    protected $messages = [
        'newDirectoryName.required' => 'Der Verzeichnisname ist erforderlich.',
        'newDirectoryName.unique' => 'Dieses Verzeichnis existiert bereits.',
        'newDirectoryName.not_regex' => 'Der Name enthält ungültige Zeichen.',
        'editingDirectoryName.required' => 'Der Verzeichnisname ist erforderlich.',
        'editingDirectoryName.unique' => 'Dieses Verzeichnis existiert bereits.',
        'editingDirectoryName.not_regex' => 'Der Name enthält ungültige Zeichen.',
        'uploads.*.max' => 'Die Datei darf maximal 10MB groß sein.',
    ];

    public function mount()
    {
        $this->users = $this->loadUsers();
    }

    // KORREKTUR: Setzt die Paginierung zurück, wenn der Suchbegriff geändert wird.
    public function updatedSearch()
    {
        $this->resetPage();
    }

    private function loadUsers(): array
    {
        $adminUsers = \App\Models\Admin::get()->map(fn($u) => ['key' => 'admin:' . $u->id, 'name' => $u->first_name . ' ' . $u->last_name, 'type' => 'Admin']);
        $employeeUsers = \App\Models\Employee::get()->map(fn($u) => ['key' => 'employee:' . $u->id, 'name' => $u->first_name . ' ' . $u->last_name, 'type' => 'Employee']);
        $customerUsers = \App\Models\Customer::get()->map(fn($u) => ['key' => 'customer:' . $u->id, 'name' => $u->first_name . ' ' . $u->last_name, 'type' => 'Customer']);
        return $adminUsers->merge($employeeUsers)->merge($customerUsers)->sortBy('name')->values()->toArray();
    }

    public function createDirectory()
    {
        $this->validateOnly('newDirectoryName');
        $path = 'public/directories/' . $this->newDirectoryName;
        Storage::makeDirectory($path);
        Directory::create(['name' => $this->newDirectoryName, 'path' => $path]);
        $this->reset('newDirectoryName');
        $this->dispatch('notify', message: 'Verzeichnis erfolgreich erstellt!');
    }

    public function deleteDirectory(int $id)
    {
        $directory = Directory::findOrFail($id);
        Storage::deleteDirectory($directory->path);
        $directory->delete();
        if ($this->selectedDirectory?->id === $id) {
            $this->unselectDirectory();
        }
        $this->dispatch('notify', message: 'Verzeichnis wurde gelöscht.');
    }

    public function startEditing(int $id)
    {
        $this->unselectDirectory();
        $directory = Directory::findOrFail($id);
        $this->editingDirectoryId = $directory->id;
        $this->editingDirectoryName = $directory->name;
    }

    public function updateDirectoryName()
    {
        $this->validateOnly('editingDirectoryName');
        $directory = Directory::findOrFail($this->editingDirectoryId);
        $newPath = 'public/directories/' . $this->editingDirectoryName;
        Storage::move($directory->path, $newPath);
        $directory->update(['name' => $this->editingDirectoryName, 'path' => $newPath]);
        $this->cancelEditing();
        $this->dispatch('notify', message: 'Verzeichnis wurde umbenannt.');
    }

    public function cancelEditing()
    {
        $this->reset('editingDirectoryId', 'editingDirectoryName');
    }

    public function selectDirectory(int $id)
    {
        $this->cancelEditing();
        $this->resetPage('filesPage');
        $this->selectedDirectory = Directory::with(['admins', 'employees', 'customers'])->findOrFail($id);
        $assigned = collect();
        $assigned = $assigned->merge($this->selectedDirectory->admins->map(fn($u) => 'admin:' . $u->id));
        $assigned = $assigned->merge($this->selectedDirectory->employees->map(fn($u) => 'employee:' . $u->id));
        $assigned = $assigned->merge($this->selectedDirectory->customers->map(fn($u) => 'customer:' . $u->id));
        $this->assignedUsers = $assigned->values()->toArray();
    }

    public function syncUsers()
    {
        if (!$this->selectedDirectory) return;
        $relations = ['admin' => [], 'employee' => [], 'customer' => []];
        foreach ($this->assignedUsers as $userKey) {
            [$type, $id] = explode(':', $userKey);
            $relations[$type][] = $id;
        }
        $this->selectedDirectory->admins()->sync($relations['admin']);
        $this->selectedDirectory->employees()->sync($relations['employee']);
        $this->selectedDirectory->customers()->sync($relations['customer']);
        $this->dispatch('notify', message: 'Benutzerzuweisungen gespeichert!');
    }

    public function unselectDirectory()
    {
        // Beachte: 'search' wird hier zurückgesetzt, wenn ein Verzeichnis abgewählt wird.
        // Wenn die Suche erhalten bleiben soll, entferne 'search' aus dieser Zeile.
        $this->reset('selectedDirectory', 'assignedUsers', 'uploads', 'search');
        $this->resetPage();
    }

    public function getFiles(): Collection
    {
        if (!$this->selectedDirectory) return collect();

        // Hinweis: Diese Methode verwendet auch die Eigenschaft '$search'.
        // Wenn du separate Suchen für Dateien und Verzeichnisse willst,
        // müsstest du hier eine andere Eigenschaft verwenden (z.B. '$fileSearch').
        return collect(Storage::files($this->selectedDirectory->path))
            ->filter(fn($file) => empty($this->search) || str_contains(strtolower(basename($file)), strtolower($this->search)))
            ->map(function ($file) {
                $name = basename($file);
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                return [
                    'name' => $name,
                    'isImage' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']),
                    'url' => Storage::url($file),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    public function uploadFiles()
    {
        if (!$this->selectedDirectory || empty($this->uploads)) return;
        $this->validateOnly('uploads.*');
        foreach ($this->uploads as $file) {
            $filename = $file->getClientOriginalName();
            $file->storeAs($this->selectedDirectory->path, $filename);
        }
        $this->reset('uploads');
        $this->js("document.querySelector('input[type=\"file\"]').value = '';");
        $this->dispatch('notify', message: 'Dateien erfolgreich hochgeladen!');
    }

    public function deleteFile(string $filename)
    {
        if (!$this->selectedDirectory) return;
        Storage::delete($this->selectedDirectory->path . '/' . $filename);
        $this->dispatch('notify', message: 'Datei wurde gelöscht.');
    }

    public function render()
    {
        $directoriesQuery = Directory::withCount(['admins', 'employees', 'customers'])
            ->orderBy('name');

        // KORREKTUR: Die Suchlogik wird hier angewendet.
        if (!empty($this->search)) {
            $directoriesQuery->where('name', 'like', '%' . $this->search . '%');
        }

        $directories = $directoriesQuery->paginate(10);

        $allFiles = $this->getFiles();
        $perPage = 5;
        $currentPage = LengthAwarePaginator::resolveCurrentPage('filesPage');
        $currentPageItems = $allFiles->slice(($currentPage - 1) * $perPage, $perPage)->all();
        $paginatedFiles = new LengthAwarePaginator($currentPageItems, $allFiles->count(), $perPage, $currentPage, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'filesPage',
        ]);

        return view('livewire.admin.directory-manager', [
            'directories' => $directories,
            'files' => $paginatedFiles,
        ]);
    }
}
