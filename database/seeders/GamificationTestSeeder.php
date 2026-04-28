<?php

namespace Database\Seeders;

use App\Models\Customer\Customer;
use App\Models\Customer\CustomerGamification;
use App\Services\Gamification\GameConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class GamificationTestSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('de_DE');

        $this->command->info('Starte Gamification Test-Seeding...');

        // Vorhandene Test-Gamer löschen, damit der Seeder mehrfach ausführbar bleibt
        $testCustomers = Customer::where('email', 'like', '%@test-gamer.de')->get();
        foreach ($testCustomers as $c) {
            $c->forceDelete();
        }

        $password = Hash::make('password');

        // =========================================================
        // SZENARIO 1: DER UNSTERBLICHE SEELENGOTT 1
        // =========================================================
        $this->command->info('Erstelle Szenario: Unsterblicher Seelengott 1...');
        $god = $this->createCustomer('Seelen', 'Gott', 'gott@test-gamer.de', $password);

        $allDiamondTitles = [];
        foreach (GameConfig::getTitles() as $key => $data) {
            $allDiamondTitles[$key] = 9999;
        }

        CustomerGamification::create([
            'customer_id' => $god->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'level' => 10,
            'funken_balance' => 5000,
            'funken_total_earned' => 85000,
            'energy_balance' => 5,
            'titles_progress' => $allDiamondTitles,
            'active_title' => 'spieler'
        ]);

        // =========================================================
        // SZENARIO 2: DER UNSTERBLICHE SEELENGOTT 2 (Titan)
        // =========================================================
        $this->command->info('Erstelle Szenario: Unsterblicher Seelengott 2 (Titan)...');
        $titan = $this->createCustomer('Ewiger', 'Titan', 'titan@test-gamer.de', $password);

        CustomerGamification::create([
            'customer_id' => $titan->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'level' => 10,
            'funken_balance' => 3000,
            'funken_total_earned' => 82000,
            'energy_balance' => 5,
            'titles_progress' => $allDiamondTitles,
            'active_title' => 'funkenkoenig'
        ]);

        // =========================================================
        // SZENARIO 3: DER DATENSCHUTZ-GEIST (Darf nicht im Ranking sein)
        // =========================================================
        $this->command->info('Erstelle Szenario: Der unsichtbare Platz 1...');
        $ghost = $this->createCustomer('Invisible', 'Ghost', 'ghost@test-gamer.de', $password);
        CustomerGamification::create([
            'customer_id' => $ghost->id,
            'is_active' => true,
            'ranking_opt_in' => false, // WICHTIG: Darf nicht im Ranking auftauchen!
            'level' => 10,
            'funken_balance' => 99999,
            'funken_total_earned' => 999999,
            'energy_balance' => 5,
            'titles_progress' => $allDiamondTitles,
        ]);

        // =========================================================
        // SZENARIO 4: DER ERSCHÖPFTE SPIELER (0 Energie)
        // =========================================================
        $this->command->info('Erstelle Szenario: Spieler ohne Energie...');
        $tired = $this->createCustomer('Tired', 'Gamer', 'tired@test-gamer.de', $password);
        CustomerGamification::create([
            'customer_id' => $tired->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'level' => 4,
            'funken_balance' => 120,
            'funken_total_earned' => 120,
            'energy_balance' => 0, // WICHTIG: 0 Energie!
            'titles_progress' => $this->getEmptyTitles(),
        ]);

        // =========================================================
        // SZENARIO 5: BEREIT FÜR LEVEL UP (Braucht nur noch 1 Klick)
        // =========================================================
        $this->command->info('Erstelle Szenario: Bereit für Level Up...');
        $levelUpUser = $this->createCustomer('Level', 'Upper', 'levelup@test-gamer.de', $password);
        CustomerGamification::create([
            'customer_id' => $levelUpUser->id,
            'is_active' => true,
            'ranking_opt_in' => true,
            'level' => 3,
            'funken_balance' => 100,
            'funken_total_earned' => 200,
            'energy_balance' => 5,
            'titles_progress' => $this->getEmptyTitles(),
        ]);

        // =========================================================
        // SZENARIO 6: MASSE FÜR DIE RANGLISTE (ca. 55 User)
        // =========================================================
        $this->command->info('Erstelle Masse für die Rangliste (Top 50 Check)...');
        for ($i = 1; $i <= 55; $i++) {
            $level = rand(1, 9);
            $totalEarned = $level * rand(100, 1000);
            $balance = (int)($totalEarned * 0.3);

            $user = $this->createCustomer($faker->firstName, $faker->lastName, "npc{$i}@test-gamer.de", $password);

            $randomTitles = [];
            foreach (GameConfig::getTitles() as $key => $data) {
                $randomTitles[$key] = rand(0, 50);
            }

            CustomerGamification::create([
                'customer_id' => $user->id,
                'is_active' => true,
                'ranking_opt_in' => true,
                'level' => $level,
                'funken_balance' => $balance,
                'funken_total_earned' => $totalEarned,
                'energy_balance' => rand(1, 5),
                'titles_progress' => $randomTitles,
            ]);
        }

        $this->command->info('Gamification Test-Daten erfolgreich generiert!');
    }

    /**
     * Erstellt den User und aktualisiert korrekt das dazugehörige Profil.
     */
    private function createCustomer($first, $last, $email, $password)
    {
        // 1. Customer Datensatz anlegen (Ohne email_verified_at, da das im Profile liegt)
        $customer = Customer::create([
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'password' => $password,
        ]);

        // 2. Das Profil updaten (Wird von Customer::boot() eventuell schon leer angelegt)
        if ($customer->profile) {
            $customer->profile->update([
                'city' => 'Test-Stadt',
                'email_verified_at' => now(), // HIER wird die Mail verifiziert!
            ]);
        } else {
            $customer->profile()->create([
                'city' => 'Test-Stadt',
                'email_verified_at' => now(), // Fallback
            ]);
        }

        return $customer;
    }

    private function getEmptyTitles(): array
    {
        $titles = [];
        foreach (GameConfig::getTitles() as $key => $data) {
            $titles[$key] = 0;
        }
        return $titles;
    }
}
