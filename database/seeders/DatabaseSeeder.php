<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            /*UserSeeder::class,*/
            ProductSeeder::class,               // Für den Livegang notwendig
            ShippingSeeder::class,              // Für den Livegang notwendig
            ShopSettingSeeder::class,           // Für den Livegang notwendig
            BlogCategorySeeder::class,          // Für den Livegang notwendig
            FinancialSeeder::class,             // Für den Livegang notwendig
            CategorySeeder::class,              // Für den Livegang notwendig
            ShopAttributeSeeder::class,             // Für den Livegang notwendig
            OrdersTableSeeder::class,               // DEAKTIVIEREN BEI LIVEGANG
            BlogSeeder::class,                      // Für den Livegang notwendig
            NewsletterTemplateSeeder::class,        // Für den Livegang notwendig
            // ProjectMasterSeeder::class,             // DEAKTIVIEREN BEI LIVEGANG
        ]);

    }
}
