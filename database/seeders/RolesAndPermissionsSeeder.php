<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        // ===================================================================
        // TEIL 1: ROLLEN UND BERECHTIGUNGEN ERSTELLEN
        // ===================================================================

        // 1. Rollen erstellen oder laden
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $customerRole = Role::firstOrCreate(['name' => 'customer']);
        $employeeRole = Role::firstOrCreate(['name' => 'employee']);

        // 2. Alle Berechtigungen definieren
        $permissions = [
            'manage_admins', 'manage_admin_profiles', 'manage_customers',
            'manage_employees', 'manage_roles', 'delete_account'
        ];

        // 3. Alle Berechtigungen erstellen oder laden
        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName]);
        }

        // 4. Berechtigungen den Rollen zuweisen
        $adminRole->permissions()->sync(Permission::all());
        $customerRole->permissions()->sync(Permission::where('name', 'delete_account')->first());
        $employeeRole->permissions()->sync(Permission::where('name', 'delete_account')->first());


        // ===================================================================
        // TEIL 2: BENUTZER ERSTELLEN UND IHNEN ROLLEN ZUWEISEN
        // ===================================================================

        // 5. Admin-Benutzer erstellen und die Admin-Rolle zuweisen
        $admin = Admin::firstOrCreate(
            ['email' => 'kontakt@mein-seelenfunke.de'],
            [
                'first_name' => 'Alina',
                'last_name' => 'Steinhauer',
                'password' => Hash::make('SeelenPower123+++'),
            ]
        );
        $admin->roles()->sync($adminRole->id);

        // 6. Kunden-Benutzer erstellen und die Kunden-Rolle zuweisen
        $customer = Customer::firstOrCreate(
            ['email' => 'alina.stone@t-online.de'],
            [
                'first_name' => 'Melanie',
                'last_name' => 'Musterfrau',
                'password' => Hash::make('SeelenPower123+++'),
            ]
        );
        $customer->roles()->sync($customerRole->id);

        // WICHTIG: Profil mit Adresse für den Kunden anlegen
        // Damit beim Login im Checkout die Daten direkt geladen werden können.
        $customer->profile()->updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'street' => 'Carl-Goerdeler-Ring',
                'house_number' => '26',
                'postal' => '38518',
                'city' => 'Gifhorn',
                'phone_number' => '0151 12345678',
            ]
        );

        // 7. Mitarbeiter-Benutzer erstellen und die Mitarbeiter-Rolle zuweisen
        $employee = Employee::firstOrCreate(
            ['email' => 'mitarbeiter@mein-seelenfunke.de'],
            [
                'first_name' => 'Mitarbeiter Vorname',
                'last_name' => 'Mitarbeiter Nachname',
                'password' => Hash::make('SeelenPower123+++'),
            ]
        );
        $employee->roles()->sync($employeeRole->id);
    }
}
