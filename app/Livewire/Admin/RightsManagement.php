<?php

namespace App\Livewire\Admin;

use App\Models\Permission;
use App\Models\Role;
use App\Models\FunkiLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class RightsManagement extends Component
{
    public $activeTab = 'roles'; // 'roles' oder 'logs'
    public $searchPermission = '';

    protected $listeners = ['refreshComponent' => '$refresh'];

    /**
     * Fügt eine Berechtigung einer Rolle hinzu (Drag & Drop Ziel)
     */
    public function addPermissionToRole($roleId, $permissionId)
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        if ($role->permissions()->where('permission_id', $permissionId)->exists()) {
            $this->dispatch('notify', ['type' => 'info', 'message' => 'Berechtigung bereits vorhanden.']);
            return;
        }

        $role->permissions()->attach($permissionId);

        // Logging
        FunkiLog::create([
            'type' => 'system',
            'action_id' => 'rights:attach',
            'title' => 'Recht zugewiesen',
            'message' => "Admin " . Auth::user()->first_name . " hat der Rolle '{$role->name}' das Recht '{$permission->name}' zugewiesen.",
            'status' => 'success',
            'payload' => ['role' => $role->name, 'permission' => $permission->name],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Recht erfolgreich zugewiesen.']);
    }

    /**
     * Entfernt eine Berechtigung von einer Rolle
     */
    public function removePermissionFromRole($roleId, $permissionId)
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        $role->permissions()->detach($permissionId);

        // Logging
        FunkiLog::create([
            'type' => 'warning',
            'action_id' => 'rights:detach',
            'title' => 'Recht entzogen',
            'message' => "Admin " . Auth::user()->first_name . " hat der Rolle '{$role->name}' das Recht '{$permission->name}' entzogen.",
            'status' => 'success',
            'payload' => ['role' => $role->name, 'permission' => $permission->name],
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->dispatch('notify', ['type' => 'success', 'message' => 'Recht entfernt.']);
    }

    public function render()
    {
        $permissions = Permission::query()
            ->when($this->searchPermission, function($q) {
                $q->where('name', 'like', '%' . $this->searchPermission . '%');
            })
            ->orderBy('name')
            ->get();

        $roles = Role::with('permissions')->get();

        $logs = FunkiLog::where('action_id', 'like', 'rights:%')
            ->latest()
            ->paginate(10, ['*'], 'logPage');

        return view('livewire.admin.rights-management', [
            'permissions' => $permissions,
            'roles' => $roles,
            'logs' => $logs
        ]);
    }
}
