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
            ProductSeeder::class,                   // Für den Livegang notwendig
            ShippingSeeder::class,                  // Für den Livegang notwendig
            ShopSettingSeeder::class,               // Für den Livegang notwendig
            BlogCategorySeeder::class,              // Für den Livegang notwendig
            FinancialSeeder::class,                 // Für den Livegang notwendig
            CategorySeeder::class,                  // Für den Livegang notwendig
            ShopAttributeSeeder::class,             // Für den Livegang notwendig
            /*OrdersTableSeeder::class,*/           // DEAKTIVIEREN BEI LIVEGANG
            BlogSeeder::class,                      // Für den Livegang notwendig
            NewsletterKampagnenSeeder::class,       // Für den Livegang notwendig
            MonthlyVoucherSeeder::class,            // Für den Livegang notwendig

            /*TodoSeeder::class,*/                  // DEAKTIVIEREN BEI LIVEGANG
            FunkiDayRoutineSeeder::class,           // Für den Livegang notwendig
            MapSeeder::class,                       // Für den Livegang notwendig
            /*ProductReviewSeeder::class,*/         // DEAKTIVIEREN BEI LIVEGANG

            KnowledgeBaseSeeder::class,             // Für den Livegang notwendig
           /* GamificationTestSeeder::class,*/          // DEAKTIVIEREN BEI LIVEGANG
            FinancialData2028Seeder::class,          // DEAKTIVIEREN BEI LIVEGANG
            PersonProfileSeeder::class,             // Für den Livegang notwendig
        ]);

    }
}
