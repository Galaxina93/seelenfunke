<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // ===================================================================
        // BENUTZER ERSTELLEN
        // ===================================================================

        // 1. Admin-Benutzer (Alina) erstellen und Profil anlegen
        $admin = Admin::firstOrCreate(
            ['email' => 'kontakt@mein-seelenfunke.de'],
            [
                'first_name' => 'Alina',
                'last_name' => 'Steinhauer',
                'password' => Hash::make('SeeleSeele1993+++'),
            ]
        );

        // Profil für Admin (Alina)
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
                'photo_path' => 'shop/projekt/about/gruender-profil.webp',
            ]
        );

        // 2. Kunden-Benutzer (Neues Profil: Sarah)
        $customer = Customer::firstOrCreate(
            ['email' => 'alina.stone@t-online.de'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Sonnenschein',
                'password' => Hash::make('SeeleSeele1993+++'),
            ]
        );

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

        // 3. Mitarbeiter-Benutzer
        $employee = Employee::firstOrCreate(
            ['email' => 'mitarbeiter@mein-seelenfunke.de'],
            [
                'first_name' => 'Marc',
                'last_name' => 'Mitarbeiter',
                'password' => Hash::make('SeeleSeele1993+++'),
            ]
        );

        // Profil für Mitarbeiter
        $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'city' => 'Wolfsburg',
                'country' => 'DE',
                'email_verified_at' => now(),
            ]
        );
    }
}
