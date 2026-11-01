<?php

namespace Database\Seeders;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use App\Services\Gamification\GamificationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GamificationUltimatePlayerSeeder extends Seeder
{
    /**
     * Erstellt bzw. aktualisiert die Entwickler-Test-Kundenkonten für das Seelenfunken-Treueprogramm:
     * - Funki Level 10 (Gottkaiser des Seelenfunkens) inkl. aller 10 Stufen-Hintergründe
     * - 5.000 Seelenfunken Guthaben & 5.000 Lebenszeit-Funken
     */
    public function run(): void
    {
        $gameService = app(GamificationService::class);
        $gameService->ensureHangarColumns();

        // 1. Dedizierter Ultimate-Dev-Account
        $imperator = Customer::firstOrCreate(
            ['email' => 'imperator@mein-seelenfunke.de'],
            [
                'first_name' => 'Astral',
                'last_name'  => 'Imperator',
                'password'   => Hash::make('SeeleSeele1993+++'),
            ]
        );
        $this->ensureVerifiedProfile($imperator);

        // 2. Standard-Kundenaccount aus UserSeeder (Sarah Sonnenschein)
        $sarah = Customer::where('email', 'alina.stone@t-online.de')->first();
        if ($sarah) {
            $this->ensureVerifiedProfile($sarah);
        }

        // 3. Aktiver Entwickler-Account aus der aktuellen Session (falls vorhanden)
        $sessionDevUser = Customer::find('16c5de17-4d05-44c0-aae6-aa48c3a553e0');

        // 4. Optionaler Test-Gott aus GamificationTestSeeder
        $testGod = Customer::where('email', 'gott@test-gamer.de')->first();

        $targets = collect([$imperator, $sarah, $sessionDevUser, $testGod])
            ->filter()
            ->unique('id')
            ->values();

        foreach ($targets as $idx => $customer) {
            $this->seedUltimateLoadoutForCustomer($customer, $idx === 0 ? 'Imperator Sol' : null);
        }

        if ($this->command) {
            $this->command->info('✨ GamificationUltimatePlayerSeeder: ' . $targets->count() . ' Entwickler-Accounts mit Funki Level 10 & 5.000 Seelenfunken ausgestattet!');
        }
    }

    private function ensureVerifiedProfile(Customer $customer): void
    {
        if ($customer->profile) {
            $customer->profile->update([
                'email_verified_at' => $customer->profile->email_verified_at ?? now(),
                'city'              => $customer->profile->city ?: 'Gifhorn',
                'country'           => $customer->profile->country ?: 'DE',
            ]);
        } else {
            $customer->profile()->create([
                'street'             => 'Sternenring',
                'house_number'       => '1',
                'postal'             => '38518',
                'city'               => 'Gifhorn',
                'country'            => 'DE',
                'email_verified_at'  => now(),
                'ai_data_consent'    => true,
                'ai_data_consent_at' => now(),
            ]);
        }
    }

    public function seedUltimateLoadoutForCustomer(Customer $customer, ?string $preferredPlayerName = null): CustomerGamification
    {
        $existingProfile = CustomerGamification::where('customer_id', $customer->id)->first();

        return CustomerGamification::updateOrCreate(
            ['customer_id' => $customer->id],
            [
                'is_active'            => true,
                'ranking_opt_in'       => true,
                'player_name'          => $existingProfile?->player_name ?: ($preferredPlayerName ?: ($customer->first_name . ' ' . substr((string) $customer->last_name, 0, 1) . '.')),
                'use_real_name'        => true,
                'level'                => GameConfig::MAX_LEVEL,
                'funki_level'          => GameConfig::MAX_LEVEL,
                'funken_balance'       => 5000,
                'funken_total_earned'  => 5000,
                'unlocked_backgrounds' => array_keys(GameConfig::getCockpitBackgrounds()),
                'active_background'    => 'eternal_god_emperor',
            ]
        );
    }
}
