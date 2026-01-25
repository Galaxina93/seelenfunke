<?php

namespace App\Livewire\Global\Crud;

use App\Models\Permission;
use App\Models\Role;

class RightsCrud extends UniversalCrud
{
    public array $selectedPermission = [];

    public function mount(string $configClass = null): void
    {
        parent::mount('crud\\RightsConfig');
        $this->config['fields']['permissions']['data']['permissions'] = Permission::all();
        $this->config['fields']['permissions']['data']['roles'] = Role::with('permissions')->get();
    }

    // CRUD
    public function addPermissionToRole(string $roleId): void
    {
        // Hole die ausgewählte Berechtigungs-ID aus dem Array
        $permissionId = $this->selectedPermission[$roleId] ?? null;

        // Führe die Aktion nur aus, wenn eine Berechtigung ausgewählt wurde
        if (!$permissionId) {
            // Optional: Eine Fehlermeldung anzeigen
            $this->addError('permissionError', 'Bitte wählen Sie eine Berechtigung aus.');
            return;
        }

        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        // Setze die Auswahl für diese Rolle zurück
        unset($this->selectedPermission[$roleId]);

        // Lade die Daten neu
        $this->refresh();
    }

    public function deletePermissionFromRole(string $roleId, string $permissionId): void
    {
        $role = Role::findOrFail($roleId);
        $permission = Permission::findOrFail($permissionId);
        $role->permissions()->detach($permission);
        $this->refresh();
    }

    // Refresh-Methode
    public function refresh(): void
    {
        $this->config['fields']['permissions']['data']['permissions'] = Permission::all();
        $this->config['fields']['permissions']['data']['roles'] = Role::with('permissions')->get();
    }
}
