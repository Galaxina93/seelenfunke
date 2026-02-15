<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Faker\Factory as Faker;

// Models importieren
use App\Models\Admin\Admin;
use App\Models\Customer\Customer;
use App\Models\Role;
use App\Models\Permission;
use App\Models\Product\Product;
use App\Models\Product\ProductTierPrice;
use App\Models\Category;
use App\Models\Order\Order;
use App\Models\Order\OrderItem;
use App\Models\Financial\FinanceGroup;
use App\Models\Financial\FinanceCostItem;
use App\Models\Financial\FinanceCategory;
use App\Models\Financial\FinanceSpecialIssue;
use App\Models\Blog\BlogCategory;
use App\Models\Blog\BlogPost;
use App\Models\NewsletterTemplate;

class ProjectMasterSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('de_DE');

        // Wir rufen den ersten verfügbaren Admin ab, um Finanz- und Blogdaten zuzuordnen
        $admin = Admin::first();
        if (!$admin) {
            $this->command->error('Kein Admin gefunden! Bitte stelle sicher, dass Admins vor diesem Seeder erstellt werden.');
            return;
        }

        $this->command->info('Starte Master-Seeding (Kunden, Produkte, Finanzen, Content)...');

        // ===================================================================
        // 1. KUNDEN-USER (Sarah Sonnenschein)
        // ===================================================================
        $customerRole = Role::where('name', 'customer')->first();

        $customerSarah = Customer::firstOrCreate(
            ['email' => 'alina.stone@t-online.de'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Sonnenschein',
                'password' => Hash::make('SeelenPower123+++'),
            ]
        );
        if ($customerRole) {
            $customerSarah->roles()->syncWithoutDetaching([$customerRole->id]);
        }

        $customerSarah->profile()->updateOrCreate(
            ['customer_id' => $customerSarah->id],
            [
                'street' => 'Lindenstraße',
                'house_number' => '12a',
                'postal' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'phone_number' => '+49 176 99887766',
            ]
        );

        // ===================================================================
        // 2. PRODUKTE & KATEGORIEN
        // ===================================================================
        $shopCats = [
            'Glas & Kristall' => 'physical',
            'Schmuck & Anhänger' => 'physical',
            'E-Books & Guides' => 'digital'
        ];

        foreach ($shopCats as $name => $type) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'type' => $type, 'color' => 'bg-blue-50 text-blue-700']
            );
        }

        // Hauptprodukt: Der Seelen Kristall
        $crystal = Product::updateOrCreate(
            ['slug' => 'seelen-kristall'],
            [
                'name' => 'Der Seelen Kristall',
                'type' => 'physical',
                'short_description' => 'Personalisiertes 3D-Glasgeschenk inkl. Geschenkbox.',
                'price' => 3990,
                'sku' => 'SK-001',
                'status' => 'active',
                'quantity' => 150,
                'track_quantity' => true,
                'weight' => 930,
                'configurator_settings' => [
                    'allow_text_pos' => true,
                    'allow_logo' => true,
                    'area_shape' => 'rect',
                    'area_top' => 10, 'area_left' => 10, 'area_width' => 80, 'area_height' => 80
                ],
                'attributes' => ['Material' => 'K9 Kristallglas', 'Größe' => '160x180x40 mm'],
                'completion_step' => 4
            ]
        );
        $crystal->categories()->sync([Category::first()->id]);

        // Staffelpreise
        ProductTierPrice::firstOrCreate(
            ['product_id' => $crystal->id, 'qty' => 10],
            ['percent' => 10.00]
        );

        // ===================================================================
        // 3. BESTELLHISTORIE (Für Dashboard Charts & Sarahs Profil)
        // ===================================================================
        for ($i = 0; $i < 15; $i++) {
            $orderDate = Carbon::now()->subDays(rand(1, 60));
            $subtotal = rand(3990, 12000);
            $order = Order::create([
                'order_number' => 'ORD-' . $orderDate->format('Y') . '-' . strtoupper(Str::random(6)),
                'customer_id' => $customerSarah->id,
                'email' => $customerSarah->email,
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal_price' => $subtotal,
                'tax_amount' => $subtotal * 0.19,
                'total_price' => $subtotal * 1.19,
                'created_at' => $orderDate,
                'billing_address' => $customerSarah->profile->toArray(),
                'shipping_address' => $customerSarah->profile->toArray()
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $crystal->id,
                'product_name' => $crystal->name,
                'quantity' => rand(1, 2),
                'unit_price' => 3990,
                'total_price' => 3990
            ]);
        }

        // ===================================================================
        // 4. FINANZEN (Zugeordnet zum existierenden Admin)
        // ===================================================================
        $group = FinanceGroup::firstOrCreate(
            ['admin_id' => $admin->id, 'name' => 'Betriebsausgaben'],
            ['type' => 'expense']
        );

        FinanceCostItem::firstOrCreate(
            ['name' => 'Werkstatt-Miete'],
            [
                'finance_group_id' => $group->id,
                'amount' => -500.00,
                'interval_months' => 1,
                'first_payment_date' => now()->startOfYear(),
                'is_business' => true
            ]
        );

        FinanceCategory::firstOrCreate(['admin_id' => $admin->id, 'name' => 'Marketing']);

        FinanceSpecialIssue::create([
            'admin_id' => $admin->id,
            'title' => 'Social Media Werbeanzeigen',
            'amount' => -300.00,
            'category' => 'Marketing',
            'execution_date' => now()->subDays(15),
            'is_business' => true
        ]);

        // ===================================================================
        // 5. CONTENT (BLOG & NEWSLETTER)
        // ===================================================================
        $blogCat = BlogCategory::firstOrCreate(['name' => 'Magazin', 'slug' => 'magazin']);

        BlogPost::firstOrCreate(
            ['slug' => 'die-kunst-der-lasergravur'],
            [
                'user_id' => $admin->id,
                'blog_category_id' => $blogCat->id,
                'title' => 'Die Kunst der Lasergravur',
                'content' => 'Erfahren Sie, wie wir Licht nutzen, um Momente in Glas zu bannen...',
                'status' => 'published',
                'published_at' => now()->subDays(5),
            ]
        );

        NewsletterTemplate::firstOrCreate(
            ['target_event_key' => 'sale_winter'],
            [
                'title' => 'Winter Sale Vorbereitung',
                'subject' => '❄️ Funkelnde Winterangebote',
                'content' => 'Bald ist es soweit...',
                'days_offset' => 7,
                'is_active' => true
            ]
        );

        $this->command->info('Master-Seeding erfolgreich abgeschlossen! 💎');
    }
}
