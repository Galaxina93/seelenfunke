<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\System\SystemPermission;
use App\Models\System\SystemRole;
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
        $adminRole = SystemRole::firstOrCreate(['name' => 'admin']);
        $customerRole = SystemRole::firstOrCreate(['name' => 'customer']);
        $employeeRole = SystemRole::firstOrCreate(['name' => 'employee']);

        // 2. Alle Berechtigungen definieren
        $permissions = [
            'manage_admins', 'manage_admin_profiles', 'manage_customers',
            'manage_employees', 'manage_roles', 'delete_account'
        ];

        // 3. Alle Berechtigungen erstellen oder laden
        foreach ($permissions as $permissionName) {
            SystemPermission::firstOrCreate(['name' => $permissionName]);
        }

        // 4. Berechtigungen den Rollen zuweisen
        $adminRole->permissions()->sync(SystemPermission::all());
        $customerRole->permissions()->sync(SystemPermission::where('name', 'delete_account')->get());
        $employeeRole->permissions()->sync(SystemPermission::where('name', 'delete_account')->get());


        // ===================================================================
        // TEIL 2: BENUTZER ERSTELLEN UND IHNEN ROLLEN ZUWEISEN
        // ===================================================================

        // 5. Admin-Benutzer (Alina) erstellen und Profil anlegen
        $admin = Admin::firstOrCreate(
            ['email' => 'kontakt@mein-seelenfunke.de'],
            [
                'first_name' => 'Alina',
                'last_name' => 'Steinhauer',
                'password' => Hash::make('SeeleSeele1993+++'),
            ]
        );
        $admin->roles()->sync($adminRole->id);

        // Profil für Admin (Alina) - Gleiche Adresse wie im Kunden-Wunsch
        $admin->profile()->updateOrCreate(
            ['admin_id' => $admin->id],
            [
                'street' => 'Carl-Goerdeler-Ring',
                'house_number' => '26',
                'postal' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
                'phone_number' => '+49 1590 1966864',
                'email_verified_at' => now(),
                'birthday' => '1993-09-01',
                'photo_path' => 'images/projekt/about/gruender-profil.webp',
            ]
        );

        // 6. Kunden-Benutzer (Neues Profil: Sarah)
        $customer = Customer::firstOrCreate(
            ['email' => 'alina.stone@t-online.de'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Sonnenschein',
                'password' => Hash::make('SeeleSeele1993+++'),
            ]
        );
        $customer->roles()->sync($customerRole->id);

        // Profil für den Kunden (Sarah)
        $customer->profile()->updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'street' => 'Lindenstraße',
                'house_number' => '12a',
                'postal' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'phone_number' => '+49 176 99887766',
                'email_verified_at' => now(),
                'birthday' => '1993-09-01',
            ]
        );

        // 7. Mitarbeiter-Benutzer
        $employee = Employee::firstOrCreate(
            ['email' => 'mitarbeiter@mein-seelenfunke.de'],
            [
                'first_name' => 'Marc',
                'last_name' => 'Mitarbeiter',
                'password' => Hash::make('SeeleSeele1993+++'),
            ]
        );
        $employee->roles()->sync($employeeRole->id);

        // Optional: Profil für Mitarbeiter
        $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'city' => 'Wolfsburg',
                'country' => 'DE',
                'email_verified_at' => now(), // <-- Hier hinzugefügt
            ]
        );
    }
}
