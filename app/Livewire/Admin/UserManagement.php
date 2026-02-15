<?php

namespace App\Livewire\Admin;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\FunkiLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

class UserManagement extends Component
{
    use WithPagination;

    public $search = '';
    public $filterRole = 'all';
    public $showArchive = false;
    public $activeTab = 'users';

    public $editingId = null;
    public $editingType = null;

    public $formData = [
        'first_name' => '',
        'last_name' => '',
        'email' => '',
        'password' => '',
        'phone_number' => '',
        'street' => '',
        'house_number' => '',
        'postal' => '',
        'city' => '',
    ];

    protected $queryString = ['search', 'filterRole', 'showArchive', 'activeTab'];

    public function toggleArchive()
    {
        $this->showArchive = !$this->showArchive;
        $this->editingId = null;
        $this->resetPage();
    }

    public function startEdit($id, $type)
    {
        $this->editingId = $id;
        $this->editingType = $type;

        $model = $this->getModelInstance($type, $id);
        $this->formData = [
            'first_name' => $model->first_name,
            'last_name' => $model->last_name,
            'email' => $model->email,
            'password' => '',
            'phone_number' => $model->profile->phone_number ?? '',
            'street' => $model->profile->street ?? '',
            'house_number' => $model->profile->house_number ?? '',
            'postal' => $model->profile->postal ?? '',
            'city' => $model->profile->city ?? '',
        ];
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->editingType = null;
        $this->resetErrorBag();
    }

    public function saveInline()
    {
        $this->validate([
            'formData.first_name' => 'required|string|max:255',
            'formData.last_name' => 'required|string|max:255',
            'formData.email' => 'required|email|unique:'.($this->editingType === 'admin' ? 'admins' : ($this->editingType === 'customer' ? 'customers' : 'employees')).',email,'.$this->editingId,
        ]);

        $model = $this->getModelInstance($this->editingType, $this->editingId);

        $oldData = $model->toArray();
        $oldProfile = $model->profile ? $model->profile->toArray() : [];

        $model->update([
            'first_name' => $this->formData['first_name'],
            'last_name' => $this->formData['last_name'],
            'email' => $this->formData['email'],
        ]);

        if (!empty($this->formData['password'])) {
            $model->update(['password' => Hash::make($this->formData['password'])]);
        }

        if ($model->profile) {
            $model->profile->update([
                'phone_number' => $this->formData['phone_number'],
                'street' => $this->formData['street'],
                'house_number' => $this->formData['house_number'],
                'postal' => $this->formData['postal'],
                'city' => $this->formData['city'],
            ]);
        }

        FunkiLog::create([
            'type' => 'system',
            'action_id' => 'user:update',
            'title' => 'Profil-Mutation',
            'message' => "Datensatz von {$model->email} durch " . Auth::user()->first_name . " modifiziert.",
            'status' => 'success',
            'payload' => ['before' => array_merge($oldData, $oldProfile), 'after' => $this->formData],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->editingId = null;
        session()->flash('message', 'Änderungen am Seelenlicht gespeichert.');
    }

    public function archiveUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id);
        $model->delete();

        FunkiLog::create([
            'type' => 'system', 'action_id' => 'user:archive', 'title' => 'User archiviert',
            'message' => "Begleiter {$model->email} ins Archiv verschoben.", 'status' => 'success', 'started_at' => now(),
        ]);
    }

    public function restoreUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id, true);
        $model->restore();

        FunkiLog::create([
            'type' => 'system', 'action_id' => 'user:restore', 'title' => 'User reaktiviert',
            'message' => "Begleiter {$model->email} aus dem Archiv zurückgeholt.", 'status' => 'success', 'started_at' => now(),
        ]);
    }

    public function forceDelete($id, $type)
    {
        $model = $this->getModelInstance($type, $id, true);
        $email = $model->email;
        $model->forceDelete();

        FunkiLog::create([
            'type' => 'danger', 'action_id' => 'user:destroy', 'title' => 'User gelöscht',
            'message' => "Daten von {$email} permanent entfernt.", 'status' => 'success', 'started_at' => now(),
        ]);
    }

    protected function getModelInstance($type, $id, $withTrashed = false)
    {
        $class = match($type) { 'admin' => Admin::class, 'customer' => Customer::class, 'employee' => Employee::class };
        return $withTrashed ? $class::withTrashed()->findOrFail($id) : $class::findOrFail($id);
    }

    public function render()
    {
        $queryAdmin = Admin::query(); $queryCustomer = Customer::query(); $queryEmployee = Employee::query();

        if ($this->showArchive) {
            $queryAdmin->onlyTrashed(); $queryCustomer->onlyTrashed(); $queryEmployee->onlyTrashed();
        }

        if ($this->search) {
            $s = '%' . $this->search . '%';
            $filter = function($q) use ($s) {
                $q->where('first_name', 'like', $s)->orWhere('last_name', 'like', $s)->orWhere('email', 'like', $s)
                    ->orWhereHas('profile', function($pq) use ($s) { $pq->where('city', 'like', $s); });
            };
            $queryAdmin->where($filter); $queryCustomer->where($filter); $queryEmployee->where($filter);
        }

        $results = collect();
        if (in_array($this->filterRole, ['all', 'admin'])) $results = $results->merge($queryAdmin->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), ['user_type' => 'admin', 'last_seen' => $i->profile->last_seen ?? null])));
        if (in_array($this->filterRole, ['all', 'customer'])) $results = $results->merge($queryCustomer->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), ['user_type' => 'customer', 'last_seen' => $i->profile->last_seen ?? null])));
        if (in_array($this->filterRole, ['all', 'employee'])) $results = $results->merge($queryEmployee->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), ['user_type' => 'employee', 'last_seen' => $i->profile->last_seen ?? null])));

        $perPage = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        // Sortierung nach Aktivität (Letzter Login)
        $items = $results->sortByDesc('last_seen');
        $pagedResults = new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($currentPage, $perPage)->values(), $items->count(), $perPage, $currentPage, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        return view('livewire.admin.user-management', [
            'users' => $pagedResults,
            'logs' => FunkiLog::where('action_id', 'like', 'user:%')->latest()->paginate(10, ['*'], 'logPage')
        ]);
    }
}
