<?php

namespace App\Livewire\Admin;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Funki\FunkiLog;
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

    public $isCreating = false;
    public $createType = 'customer';

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
        'customer_type' => 'private',
        'company_name' => '',
        'vat_id' => '',
        'internal_note' => '',
        'is_verified' => true,
    ];

    protected $queryString = ['search', 'filterRole', 'showArchive', 'activeTab'];

    public function toggleArchive()
    {
        $this->showArchive = !$this->showArchive;
        $this->editingId = null;
        $this->isCreating = false;
        $this->resetPage();
    }

    public function startCreate()
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->editingType = null;
        $this->isCreating = true;
        $this->createType = 'customer';
        $this->formData = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'password' => '',
            'phone_number' => '',
            'street' => '',
            'house_number' => '',
            'postal' => '',
            'city' => '',
            'customer_type' => 'private',
            'company_name' => '',
            'vat_id' => '',
            'internal_note' => '',
            'is_verified' => true,
        ];
    }

    public function saveNewUser()
    {
        $this->validate([
            'createType' => 'required|in:admin,customer,employee',
            'formData.first_name' => 'required|string|max:255',
            'formData.last_name' => 'required|string|max:255',
            'formData.email' => 'required|email|unique:'.($this->createType === 'admin' ? 'admins' : ($this->createType === 'customer' ? 'customers' : 'employees')).',email',
            'formData.password' => 'required|string|min:8',
        ]);

        $class = match($this->createType) {
            'admin' => Admin::class,
            'customer' => Customer::class,
            'employee' => Employee::class,
        };

        $user = $class::create([
            'first_name' => $this->formData['first_name'],
            'last_name' => $this->formData['last_name'],
            'email' => $this->formData['email'],
            'password' => Hash::make($this->formData['password']),
        ]);

        if (method_exists($user, 'profile')) {
            $profile = $user->profile()->firstOrCreate([]);
            $isBusiness = $this->formData['customer_type'] === 'business';

            $profile->update([
                'phone_number' => $this->formData['phone_number'],
                'street' => $this->formData['street'],
                'house_number' => $this->formData['house_number'],
                'postal' => $this->formData['postal'],
                'city' => $this->formData['city'],
                'is_business' => $isBusiness,
                'company_name' => $isBusiness ? $this->formData['company_name'] : null,
                'vat_id' => $isBusiness ? $this->formData['vat_id'] : null,
                'internal_note' => $this->formData['internal_note'],
                'email_verified_at' => $this->formData['is_verified'] ? now() : null,
            ]);
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';

        FunkiLog::create([
            'type' => 'system',
            'action_id' => 'user:create',
            'title' => 'Neuer Benutzer angelegt',
            'message' => "Der Benutzer '{$user->email}' wurde als " . strtoupper($this->createType) . " von {$adminEmail} ins System eingefügt.",
            'status' => 'success',
            'payload' => [
                'actor' => $adminEmail,
                'user_id' => $user->id,
                'user_type' => $this->createType,
                'data' => collect($this->formData)->except('password')->toArray()
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->isCreating = false;
        session()->flash('message', 'Neues Seelenlicht erfolgreich erschaffen.');
    }

    public function startEdit($id, $type)
    {
        $this->isCreating = false;
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
            'customer_type' => ($model->profile->is_business ?? false) ? 'business' : 'private',
            'company_name' => $model->profile->company_name ?? '',
            'vat_id' => $model->profile->vat_id ?? '',
            'internal_note' => $model->profile->internal_note ?? '',
            'is_verified' => !is_null($model->profile->email_verified_at ?? null),
        ];
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->editingType = null;
        $this->isCreating = false;
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

        // Sammeln der alten Daten für den Delta-Vergleich
        $oldData = [
            'first_name' => $model->first_name,
            'last_name' => $model->last_name,
            'email' => $model->email,
            'phone_number' => $model->profile->phone_number ?? '',
            'street' => $model->profile->street ?? '',
            'house_number' => $model->profile->house_number ?? '',
            'postal' => $model->profile->postal ?? '',
            'city' => $model->profile->city ?? '',
            'customer_type' => ($model->profile->is_business ?? false) ? 'business' : 'private',
            'company_name' => $model->profile->company_name ?? '',
            'vat_id' => $model->profile->vat_id ?? '',
            'internal_note' => $model->profile->internal_note ?? '',
            'is_verified' => !is_null($model->profile->email_verified_at ?? null),
        ];

        $model->update([
            'first_name' => $this->formData['first_name'],
            'last_name' => $this->formData['last_name'],
            'email' => $this->formData['email'],
        ]);

        $passwordChanged = false;
        if (!empty($this->formData['password'])) {
            $model->update(['password' => Hash::make($this->formData['password'])]);
            $passwordChanged = true;
        }

        if ($model->profile) {
            $verifiedAt = $model->profile->email_verified_at;
            if ($this->formData['is_verified'] && !$verifiedAt) {
                $verifiedAt = now();
            } elseif (!$this->formData['is_verified']) {
                $verifiedAt = null;
            }

            $isBusiness = $this->formData['customer_type'] === 'business';

            $model->profile->update([
                'phone_number' => $this->formData['phone_number'],
                'street' => $this->formData['street'],
                'house_number' => $this->formData['house_number'],
                'postal' => $this->formData['postal'],
                'city' => $this->formData['city'],
                'is_business' => $isBusiness,
                'company_name' => $isBusiness ? $this->formData['company_name'] : null,
                'vat_id' => $isBusiness ? $this->formData['vat_id'] : null,
                'internal_note' => $this->formData['internal_note'],
                'email_verified_at' => $verifiedAt,
            ]);
        }

        // Berechne die genauen Änderungen (Delta)
        $changes = [];
        foreach ($oldData as $key => $oldValue) {
            if (isset($this->formData[$key]) && $oldValue !== $this->formData[$key]) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $this->formData[$key]
                ];
            }
        }

        if ($passwordChanged) {
            $changes['password'] = ['old' => '***', 'new' => 'wurde geändert'];
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';

        if (count($changes) > 0) {
            FunkiLog::create([
                'type' => 'system',
                'action_id' => 'user:update',
                'title' => 'Benutzerprofil modifiziert',
                'message' => "Das Profil von '{$model->email}' wurde von {$adminEmail} aktualisiert. " . count($changes) . " Felder geändert.",
                'status' => 'success',
                'payload' => [
                    'actor' => $adminEmail,
                    'target_user' => $model->email,
                    'changes' => $changes
                ],
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $this->editingId = null;
        session()->flash('message', 'Änderungen gespeichert.');
    }

    public function archiveUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id);
        $email = $model->email;
        $model->delete();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        FunkiLog::create([
            'type' => 'system',
            'action_id' => 'user:archive',
            'title' => 'Benutzer archiviert',
            'message' => "Der Benutzer '{$email}' wurde von {$adminEmail} ins Archiv verschoben.",
            'status' => 'warning',
            'payload' => ['actor' => $adminEmail, 'target_user' => $email],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function restoreUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id, true);
        $model->restore();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        FunkiLog::create([
            'type' => 'system',
            'action_id' => 'user:restore',
            'title' => 'Benutzer wiederhergestellt',
            'message' => "Der archivierte Benutzer '{$model->email}' wurde von {$adminEmail} reaktiviert.",
            'status' => 'success',
            'payload' => ['actor' => $adminEmail, 'target_user' => $model->email],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function forceDelete($id, $type)
    {
        $model = $this->getModelInstance($type, $id, true);
        $email = $model->email;
        $model->forceDelete();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        FunkiLog::create([
            'type' => 'system',
            'action_id' => 'user:force_delete',
            'title' => 'Benutzer endgültig gelöscht',
            'message' => "Achtung: Der Datensatz von '{$email}' wurde von {$adminEmail} dauerhaft aus der Datenbank entfernt.",
            'status' => 'error', // Error status signalisiert eine destruktive, kritische Aktion
            'payload' => ['actor' => $adminEmail, 'target_user' => $email],
            'started_at' => now(),
            'finished_at' => now(),
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
                $q->where('first_name', 'like', $s)
                    ->orWhere('last_name', 'like', $s)
                    ->orWhere('email', 'like', $s)
                    ->orWhereHas('profile', function($pq) use ($s) {
                        $pq->where('city', 'like', $s)->orWhere('company_name', 'like', $s);
                    });
            };
            $queryAdmin->where($filter); $queryCustomer->where($filter); $queryEmployee->where($filter);
        }

        $results = collect();
        if (in_array($this->filterRole, ['all', 'admin'])) $results = $results->merge($queryAdmin->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), ['user_type' => 'admin', 'last_seen' => $i->profile->last_seen ?? null])));
        if (in_array($this->filterRole, ['all', 'customer'])) $results = $results->merge($queryCustomer->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), ['user_type' => 'customer', 'last_seen' => $i->profile->last_seen ?? null])));
        if (in_array($this->filterRole, ['all', 'employee'])) $results = $results->merge($queryEmployee->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), ['user_type' => 'employee', 'last_seen' => $i->profile->last_seen ?? null])));

        $perPage = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $items = $results->sortByDesc('last_seen');
        $pagedResults = new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($currentPage, $perPage)->values(), $items->count(), $perPage, $currentPage, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        return view('livewire.admin.user-management', [
            'users' => $pagedResults,
            'logs' => FunkiLog::where('action_id', 'like', 'user:%')->latest()->paginate(10, ['*'], 'logPage')
        ]);
    }
}
