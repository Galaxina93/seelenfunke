<?php

namespace App\Livewire\Shop\System;

use Livewire\Attributes\Layout;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\System\SystemLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.backend_layout')]
class SystemUserManagement extends Component
{
    use WithPagination, \App\Livewire\Traits\WithDepartmentTheming;

    public string $themingDepartment = 'System';

    public $search = '';
    public $filterRole = 'all';
    public $showArchive = false;

    public $sortField = 'last_seen';
    public $sortDirection = 'desc';

    #[\Livewire\Attributes\Locked]
    public $editingId = null;
    #[\Livewire\Attributes\Locked]
    public $editingType = null;
    public bool $editing2faActive = false;

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

    protected $queryString = ['search', 'filterRole', 'showArchive', 'sortField', 'sortDirection'];

    public function boot()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

    public function mount()
    {
        if (!Auth::guard('admin')->check()) {
            abort(403);
        }
    }

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
        $this->editing2faActive = false;
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
            'formData.phone_number' => 'nullable|string|max:50',
            'formData.street' => 'nullable|string|max:255',
            'formData.house_number' => 'nullable|string|max:30',
            'formData.postal' => 'nullable|string|max:20',
            'formData.city' => 'nullable|string|max:120',
            'formData.customer_type' => 'nullable|in:private,business',
            'formData.company_name' => 'nullable|string|max:255',
            'formData.vat_id' => 'nullable|string|max:50',
            'formData.internal_note' => 'nullable|string|max:5000',
            'formData.is_verified' => 'boolean',
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
            'email_verified_at' => ($this->createType === 'customer' && $this->formData['is_verified']) ? now() : null,
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

        SystemLog::create([
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
        $this->editing2faActive = (bool) ($model->profile->two_factor_is_active ?? false);
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
            'is_verified' => $this->editingType === 'customer'
                ? (!is_null($model->email_verified_at) || !is_null($model->profile->email_verified_at ?? null))
                : !is_null($model->profile->email_verified_at ?? null),
        ];
    }

    public function cancelEdit()
    {
        $this->editingId = null;
        $this->editingType = null;
        $this->editing2faActive = false;
        $this->isCreating = false;
        $this->resetErrorBag();
    }

    public function saveInline()
    {
        if (!in_array($this->editingType, ['admin', 'customer', 'employee'], true)) {
            abort(400, 'Ungültiger Benutzertyp.');
        }

        $this->validate([
            'formData.first_name' => 'required|string|max:255',
            'formData.last_name' => 'required|string|max:255',
            'formData.email' => 'required|email|unique:'.($this->editingType === 'admin' ? 'admins' : ($this->editingType === 'customer' ? 'customers' : 'employees')).',email,'.$this->editingId,
            'formData.password' => 'nullable|string|min:8',
            'formData.phone_number' => 'nullable|string|max:50',
            'formData.street' => 'nullable|string|max:255',
            'formData.house_number' => 'nullable|string|max:30',
            'formData.postal' => 'nullable|string|max:20',
            'formData.city' => 'nullable|string|max:120',
            'formData.customer_type' => 'nullable|in:private,business',
            'formData.company_name' => 'nullable|string|max:255',
            'formData.vat_id' => 'nullable|string|max:50',
            'formData.internal_note' => 'nullable|string|max:5000',
            'formData.is_verified' => 'boolean',
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
            'is_verified' => $this->editingType === 'customer'
                ? (!is_null($model->email_verified_at) || !is_null($model->profile->email_verified_at ?? null))
                : !is_null($model->profile->email_verified_at ?? null),
        ];

        $userUpdate = [
            'first_name' => $this->formData['first_name'],
            'last_name' => $this->formData['last_name'],
            'email' => $this->formData['email'],
        ];

        if ($this->editingType === 'customer') {
            $userUpdate['email_verified_at'] = $this->formData['is_verified']
                ? ($model->email_verified_at ?? now())
                : null;
        }

        $model->update($userUpdate);

        $passwordChanged = false;
        if (!empty($this->formData['password'])) {
            $model->forceFill([
                'password' => Hash::make($this->formData['password']),
                'remember_token' => \Illuminate\Support\Str::random(60),
            ])->save();
            $passwordChanged = true;
        }

        if (method_exists($model, 'profile')) {
            $profile = $model->profile ?: $model->profile()->firstOrCreate([]);
            $verifiedAt = $profile->email_verified_at;
            if ($this->formData['is_verified'] && !$verifiedAt) {
                $verifiedAt = now();
            } elseif (!$this->formData['is_verified']) {
                $verifiedAt = null;
            }

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
            SystemLog::create([
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

    public function disable2Fa()
    {
        if (!$this->editingId || !$this->editingType) {
            return;
        }

        $this->disable2FaForUser($this->editingId, $this->editingType);
        $this->editing2faActive = false;
    }

    public function disable2FaForUser($id, $type)
    {
        $model = $this->getModelInstance($type, $id);

        if ($model->profile) {
            $model->profile->update([
                'two_factor_is_active' => false,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
            ]);
        }

        if ($this->editingId === $id && $this->editingType === $type) {
            $this->editing2faActive = false;
        }

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
            'type' => 'system',
            'action_id' => 'user:2fa_disable',
            'title' => '2-Faktor-Authentifizierung deaktiviert',
            'message' => "Die 2-Faktor-Authentifizierung für '{$model->email}' ({$type}) wurde durch Administrator {$adminEmail} zurückgesetzt.",
            'status' => 'warning',
            'payload' => [
                'actor' => $adminEmail,
                'target_user' => $model->email,
                'user_type' => $type,
                'ip' => request()->ip(),
            ],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        session()->flash('message', "2-Faktor-Authentifizierung für '{$model->email}' erfolgreich deaktiviert.");
    }

    public function archiveUser($id, $type)
    {
        if ($type === 'admin') {
            if ((string) Auth::guard('admin')->id() === (string) $id) {
                session()->flash('error', 'Sie können Ihr eigenes Administrator-Konto nicht archivieren.');
                return;
            }
            if (Admin::count() <= 1) {
                session()->flash('error', 'Der letzte verbleibende aktive Administrator kann nicht archiviert werden.');
                return;
            }
        }

        $model = $this->getModelInstance($type, $id);
        $email = $model->email;
        $model->delete();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
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
        SystemLog::create([
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
        if ($type === 'admin') {
            if ((string) Auth::guard('admin')->id() === (string) $id) {
                session()->flash('error', 'Sie können Ihr eigenes Administrator-Konto nicht löschen.');
                return;
            }
            if (Admin::withTrashed()->count() <= 1) {
                session()->flash('error', 'Der letzte verbleibende Administrator darf nicht endgültig gelöscht werden.');
                return;
            }
        }

        $model = $this->getModelInstance($type, $id, true);
        $email = $model->email;
        $model->forceDelete();

        $adminEmail = Auth::guard('admin')->user()->email ?? 'System';
        SystemLog::create([
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

    public function sortBy($column)
    {
        if (!in_array((string) $column, ['last_seen', 'login', 'identity', 'status', 'location', 'ip'], true)) {
            return;
        }
        if ($this->sortField === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $column;
            $this->sortDirection = 'desc';
        }
        $this->resetPage();
    }

    protected function getModelInstance($type, $id, $withTrashed = false)
    {
        $class = match($type) {
            'admin' => Admin::class,
            'customer' => Customer::class,
            'employee' => Employee::class,
            default => abort(400, 'Ungültiger Benutzertyp.'),
        };
        return $withTrashed ? $class::withTrashed()->findOrFail($id) : $class::findOrFail($id);
    }

    public function render()
    {
        if (!in_array((string) $this->filterRole, ['all', 'admin', 'customer', 'employee'], true)) {
            $this->filterRole = 'all';
        }
        if (!in_array((string) $this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'desc';
        }

        $queryAdmin = Admin::query(); $queryCustomer = Customer::query(); $queryEmployee = Employee::query();

        if ($this->showArchive) {
            $queryAdmin->onlyTrashed(); $queryCustomer->onlyTrashed(); $queryEmployee->onlyTrashed();
        }

        if ($this->search) {
            $terms = explode(' ', $this->search);
            foreach ($terms as $term) {
                if (empty(trim($term))) continue;
                $escapedTerm = addcslashes(trim($term), '%_\\');
                $s = '%' . $escapedTerm . '%';
                $sId = $escapedTerm . '%';
                
                $filter = function($q) use ($s, $sId) {
                    $q->where(function($subQ) use ($s, $sId) {
                        $subQ->where('first_name', 'like', $s)
                            ->orWhere('last_name', 'like', $s)
                            ->orWhere('email', 'like', $s)
                            ->orWhere('id', 'like', $sId)
                            ->orWhereHas('profile', function($pq) use ($s) {
                                $pq->where('city', 'like', $s)
                                    ->orWhere('company_name', 'like', $s)
                                    ->orWhere('phone_number', 'like', $s)
                                    ->orWhere('street', 'like', $s)
                                    ->orWhere('house_number', 'like', $s)
                                    ->orWhere('postal', 'like', $s)
                                    ->orWhere('vat_id', 'like', $s)
                                    ->orWhere('internal_note', 'like', $s);
                            });
                    });
                };
                $queryAdmin->where($filter);
                $queryCustomer->where($filter);
                $queryEmployee->where($filter);
            }
        }

        $results = collect();
        if (in_array($this->filterRole, ['all', 'admin'])) {
            $results = $results->merge($queryAdmin->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), [
                'user_type' => 'admin',
                'last_seen' => $i->profile->last_seen ?? null,
                'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                'profile' => $i->profile ? $i->profile->toArray() : []
            ])));
        }
        if (in_array($this->filterRole, ['all', 'customer'])) {
            $results = $results->merge($queryCustomer->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), [
                'user_type' => 'customer',
                'last_seen' => $i->profile->last_seen ?? null,
                'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                'profile' => $i->profile ? $i->profile->toArray() : []
            ])));
        }
        if (in_array($this->filterRole, ['all', 'employee'])) {
            $results = $results->merge($queryEmployee->with('profile')->get()->map(fn($i) => array_merge($i->toArray(), [
                'user_type' => 'employee',
                'last_seen' => $i->profile->last_seen ?? null,
                'created_at' => $i->created_at ? $i->created_at->toDateTimeString() : null,
                'profile' => $i->profile ? $i->profile->toArray() : []
            ])));
        }

        $descending = $this->sortDirection === 'desc';
        switch ($this->sortField) {
            case 'identity':
                $items = $results->sortBy(function($user) {
                    return strtolower($user['first_name'] . ' ' . $user['last_name']);
                }, SORT_REGULAR, $descending);
                break;
            case 'status':
                $items = $results->sortBy(function($user) {
                    return !empty($user['profile']['email_verified_at']) ? 1 : 0;
                }, SORT_REGULAR, $descending);
                break;
            case 'location':
                $items = $results->sortBy(function($user) {
                    return strtolower($user['profile']['city'] ?? '');
                }, SORT_REGULAR, $descending);
                break;
            case 'ip':
                $items = $results->sortBy(function($user) {
                    return $user['profile']['last_ip'] ?? '';
                }, SORT_REGULAR, $descending);
                break;
            case 'login':
            default:
                $items = $results->sortBy(function($user) {
                    return $user['last_seen'] ?? '';
                }, SORT_REGULAR, $descending);
                break;
        }

        $perPage = 15;
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $pagedResults = new \Illuminate\Pagination\LengthAwarePaginator($items->forPage($currentPage, $perPage)->values(), $items->count(), $perPage, $currentPage, ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]);

        return view('livewire.shop.system.system-user-management', [
            'users' => $pagedResults
        ]);
    }
}
