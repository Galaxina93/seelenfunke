<?php

namespace Database\Seeders;

use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Employee\Employee;
use App\Models\Marketing\MarketingInfluencer;
use App\Services\Marketing\InfluencerService;
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
                'ai_data_consent' => true,
                'ai_data_consent_at' => now(),
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
                'ai_data_consent' => true,
                'ai_data_consent_at' => now(),
            ]
        );

        // 3. Mitarbeiter-Benutzer (Marc Mitarbeiter inkl. Vertrag, Zeiterfassung & Lohnabrechnung)
        $employee = Employee::updateOrCreate(
            ['email' => 'mitarbeiter@mein-seelenfunke.de'],
            [
                'first_name' => 'Marc',
                'last_name' => 'Mitarbeiter',
                'role' => 'support',
                'password' => Hash::make('SeeleSeele1993+++'),
                'email_verified_at' => now(),
            ]
        );

        // Profil für Mitarbeiter
        $employee->profile()->updateOrCreate(
            ['employee_id' => $employee->id],
            [
                'street' => 'Schlossplatz',
                'house_number' => '4',
                'postal' => '38440',
                'city' => 'Wolfsburg',
                'country' => 'DE',
                'phone_number' => '+49 176 55443322',
                'birthday' => '1996-05-14',
                'email_verified_at' => now(),
                'ai_data_consent' => true,
                'ai_data_consent_at' => now(),
            ]
        );

        $hrService = app(\App\Services\Employee\EmployeeHrService::class);
        $hrService->ensureTablesExist();

        if (\Illuminate\Support\Facades\Schema::hasTable('employee_contracts')) {
            $contract = \App\Models\Employee\EmployeeContract::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'invitation_label' => 'Marc Mitarbeiter',
                    'job_title' => 'Manufaktur- & Versand-Assistent',
                    'department' => 'Produktion & Versand',
                    'employment_type' => 'minijob',
                    'compensation_type' => 'hourly',
                    'hourly_rate_cents' => 1400,
                    'monthly_gross_cents' => 50400,
                    'weekly_hours' => 9.0,
                    'vacation_days_per_year' => 20,
                    'vacation_days_taken' => 2,
                    'start_date' => now()->startOfYear()->toDateString(),
                    'is_fixed_term' => false,
                    'probation_months' => 3,
                    'birth_date' => '1996-05-14',
                    'birth_place' => 'Wolfsburg',
                    'nationality' => 'Deutsch',
                    'tax_id' => '12345678901',
                    'tax_class' => 'I',
                    'church_tax' => false,
                    'social_security_number' => '12140596M012',
                    'health_insurance_name' => 'Techniker Krankenkasse (TK)',
                    'health_insurance_type' => 'statutory',
                    'rv_exemption_requested' => true,
                    'has_other_employment' => false,
                    'account_holder' => 'Marc Mitarbeiter',
                    'iban' => 'DE89370400440532013000',
                    'bic' => 'COBADEFFXXX',
                    'bank_name' => 'Commerzbank',
                    'signature_name' => 'Marc Mitarbeiter',
                    'signature_data' => 'DIGITAL-SIGNATURE:Marc Mitarbeiter',
                    'signed_at' => now()->subDays(15),
                    'signed_ip_hash' => hash('sha256', '127.0.0.1'),
                    'contract_number' => 'AV-' . date('Y') . '-0001',
                    'invitation_accepted_at' => now()->subDays(15),
                    'is_active' => true,
                    'is_archived' => false,
                ]
            );

            if (\App\Models\Employee\EmployeeTimeEntry::where('contract_id', $contract->id)->count() === 0) {
                $hrService->recordTimeEntry($contract, [
                    'work_date' => now()->startOfMonth()->addDays(1)->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '13:30',
                    'break_minutes' => 0,
                    'activity_note' => 'Lasergravur & Qualitätskontrolle',
                ]);
                $hrService->recordTimeEntry($contract, [
                    'work_date' => now()->startOfMonth()->addDays(4)->toDateString(),
                    'start_time' => '09:00',
                    'end_time' => '16:00',
                    'break_minutes' => 30,
                    'activity_note' => 'Versandvorbereitung & Geschenkverpackungen',
                ]);
            }

            $hrService->generateAndStoreContractPdf($contract);
            $hrService->generateAndStoreQuestionnairePdf($contract);
            $hrService->createOrUpdatePayslip($contract, (int) now()->year, (int) now()->month);
        }

        // 4. Influencer- / Creator-Testbenutzer (Lara Living)
        $influencerEmployee = Employee::updateOrCreate(
            ['email' => 'influencer@mein-seelenfunke.de'],
            [
                'first_name' => 'Lara',
                'last_name' => 'Living',
                'role' => 'influencer',
                'password' => Hash::make('SeeleSeele1993+++'),
                'email_verified_at' => now(),
            ]
        );

        $influencerEmployee->profile()->updateOrCreate(
            ['employee_id' => $influencerEmployee->id],
            [
                'street' => 'Eppendorfer Weg',
                'house_number' => '84',
                'postal' => '20259',
                'city' => 'Hamburg',
                'country' => 'DE',
                'phone_number' => '+49 172 44556677',
                'company_name' => 'Lara Living Media',
                'is_business' => true,
                'vat_id' => '41/123/45678',
                'email_verified_at' => now(),
                'birthday' => '1997-04-15',
                'ai_data_consent' => true,
                'ai_data_consent_at' => now(),
            ]
        );

        $influencer = MarketingInfluencer::updateOrCreate(
            ['employee_id' => $influencerEmployee->id],
            [
                'handle' => '@lara.living',
                'slug' => 'lara-living',
                'primary_channel' => 'instagram',
                'commission_type' => 'percent',
                'commission_value' => 15.00,
                'follower_discount_percent' => 10,
                'cookie_days' => 30,
                'tax_status' => 'small_business',
                'tax_number' => '41/123/45678',
                'self_employed_confirmed' => true,
                'self_employed_confirmed_at' => now(),
                'payout_method' => 'bank_transfer',
                'account_holder' => 'Lara Living',
                'iban' => 'DE89370400440532013000',
                'bic' => 'COBADEFFXXX',
                'invitation_accepted_at' => now(),
                'is_active' => true,
                'internal_notes' => 'Test-Creator Account für das Seelenfunke Partnerprogramm.',
            ]
        );

        app(InfluencerService::class)->ensureTrackingLinkAndVoucher($influencer, 'LARA10');

        // 5. Steuerberater-Testbenutzer (Dr. Thomas Steuerberater)
        $taxAdvisorEmployee = Employee::updateOrCreate(
            ['email' => 'steuerberater@mein-seelenfunke.de'],
            [
                'first_name' => 'Dr. Thomas',
                'last_name' => 'Steuerberater',
                'role' => 'tax_advisor',
                'password' => Hash::make('SeeleSeele1993+++'),
                'email_verified_at' => now(),
            ]
        );

        $taxAdvisorEmployee->profile()->updateOrCreate(
            ['employee_id' => $taxAdvisorEmployee->id],
            [
                'street' => 'Steinweg',
                'house_number' => '15',
                'postal' => '38518',
                'city' => 'Gifhorn',
                'country' => 'DE',
                'phone_number' => '+49 5371 9988770',
                'company_name' => 'Kanzlei Dr. Steuerberater & Partner StBG',
                'is_business' => true,
                'vat_id' => 'DE123456789',
                'email_verified_at' => now(),
                'birthday' => '1978-11-20',
                'ai_data_consent' => true,
                'ai_data_consent_at' => now(),
            ]
        );
    }
}
