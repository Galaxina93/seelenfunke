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

            // Für den Livegang notwendig

            // System
            UserSeeder::class,
            SystemSettingSeeder::class,
            SystemMapSeeder::class,
            AiTarifeSeeder::class,
            SystemApiNodeSeeder::class,

            // Management
            ManagementDayRoutineSeeder::class,
            ManagementContactSeeder::class,
            ManagementCalenderSeeder::class,
            ManagementTaskSeeder::class,
            ManagementLinkTreeSeeder::class,

            //Product
            ProductSupplierSeeder::class,
            ProductSeeder::class,
            ProductCategorySeeder::class,
            ProductAttributeSeeder::class,
            ProductTemplateSeeder::class,

            // Marketing
            BlogCategorySeeder::class,
            MarketingBlogSeeder::class,
            NewsletterKampagnenSeeder::class,
            MonthlyVoucherSeeder::class,

            // Logistic
            LogisticsShippingSeeder::class,

            // Buchhaltung
            FinancialSeeder::class,

            // KI Agenten
            AiAgentSeeder::class,
            AiKnowledgeBaseSeeder::class,
            AiCompanyStructureSeeder::class,

            // DEAKTIVIEREN BEI LIVEGANG
            /*OrdersTableSeeder::class,*/
            /*ProductReviewSeeder::class,*/
            /*GamificationTestSeeder::class,*/
            /*FinancialDataSeeder::class,*/
            /*BankTransactionTestSeeder::class,*/
            /*OrderShoppingCartSeeder::class,*/

        ]);

    }
}
