<?php

namespace Database\Seeders;

use App\Models\Product\ProductSupplier;
use Illuminate\Database\Seeder;

class ProductSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. HÄNDLER 1: Seelenkristall
        ProductSupplier::firstOrCreate(
            ['name' => 'Pujiang Wangzhe Crafts Co., Ltd.'],
            [
                'contact_person' => 'Sales',
                'email' => '18058928838@163.COM',
                'phone' => '+86 18058928838', // WhatsApp
                'website' => 'https://yiwucrystal.en.alibaba.com/',
                'country' => 'China',
                'country_code' => 'CN',
                'lead_time_land_days' => 60,
                'lead_time_air_days' => 12,
                'shipping_method' => 'land',
                'dynamic_links' => [
                    [
                        'title' => 'Alibaba Shop', 
                        'url' => 'https://yiwucrystal.en.alibaba.com/'
                    ],
                    [
                        'title' => 'Company Profile', 
                        'url' => 'https://yiwucrystal.en.alibaba.com/company_profile.html?spm=a2700.shop_index.88.31.380f32a1tP8LGt'
                    ],
                    [
                        'title' => 'Alibaba Chat', 
                        'url' => 'https://message.alibaba.com/message/messenger.htm?spm=a2700.galleryofferlist.0.0.7f8f13a0IhXbHl&activeAccountId=275030469&activeAccountIdEncrypt=MC1IDX1uNPMq-8rLgOlAFCN3YUTsE3novz-fHgaTDeYdXvBaUIEEGCUyouX9NvuWlk1MSc2&chatToken=WTB0SE5VRlNOMHhoVVRjME5FcFROVWRMVlZOUlpqY3ZXVzV1UlhaTWRWQlNNbEp3T0d0eVREVjNhMnhSWVRNMWRVMVNVMk41ZVZBeFMyOXNaMUEyWld4WVVVNTNNMlJuWW5oemJVeDBRV1kzZWxSeVZtdEdSVUlyZVVSRlYwSmFiVk1yTDJsVk5tUk9kV2h0TjBkd2FsWkRTVkZ4TVZSQ09XMVhhVlJZVVU5aFdpOUNWRTFPUW1semMyNVNObTkyUmpoNEwzSkhNa3A0UmpVeVpIWTJhR0ZSVlhjeVIyZHJOVXRSUFE9PSZ2ZXJzaW9uPTIuMC4w'
                    ]
                ],
            ]
        );

        // 2. HÄNDLER 2: Seelenanhänger
        ProductSupplier::firstOrCreate(
            ['name' => 'Gifts Crafts Zone'],
            [
                'contact_person' => 'Store-Typ: Unternehmer',
                'email' => 'sales03@acegifts.co',
                'phone' => '+86 18728191915',
                'website' => '',
                'company_name' => 'Xipingxianyishugongyipin Co., Ltd.',
                'street' => 'Baiyuan Avenue, Henan New Packaging Materials Industrial Park, Commercial Zone D, No. 1-83',
                'zip' => '463900',
                'city' => 'Zhumadian City (Henan Province)',
                'country' => 'China',
                'country_code' => 'CN',
                'lead_time_land_days' => 14,
                'shipping_method' => 'land',
                'notes' => 'Registrierungsnummer: 91411721MAK6AACC5E (Staatliche Behörde für Marktregulierung)',
                'dynamic_links' => [],
            ]
        );

        // 3. HÄNDLER 3: Weizengläser
        ProductSupplier::firstOrCreate(
            ['name' => 'Sendez'],
            [
                'contact_person' => 'Amazon Store',
                'email' => '',
                'phone' => '',
                'website' => 'https://www.amazon.de/stores/Sendez/page/D6F1430E-D320-4385-8EDB-3D3F82362222?lp_asin=B0D6RHB544&ref_=ast_bln&store_ref=bl_ast_dp_brandlogo_sto&bl_grd_status=override',
                'notes' => 'Amazon Händler',
                'country_code' => 'DE',
                'lead_time_land_days' => 5,
                'shipping_method' => 'land',
                'dynamic_links' => [],
            ]
        );

        // 4. HÄNDLER 4: Mambocat
        ProductSupplier::firstOrCreate(
            ['name' => 'Mambocat'],
            [
                'company_name' => 'Mambocat',
                'contact_person' => 'Steffanie Prüße',
                'street' => 'Zur Finkenkuhle',
                'house_number' => '54',
                'zip' => '38259',
                'city' => 'Salzgitter-Bad',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 5341 2255089',
                'email' => 'info@mambocat.de',
                'website' => 'https://www.mambocat.de/',
                'vat_id' => 'DE274831032',
                'lead_time_land_days' => 3,
                'shipping_method' => 'land',
                'dynamic_links' => [],
            ]
        );

        // 5. HÄNDLER 5: HaoYiZF (Fuhao) – Temu (Seelen-Talisman Edelstahl-Barren)
        ProductSupplier::updateOrCreate(
            ['name' => 'HaoYiZF (Fuhao) - Temu'],
            [
                'company_name' => 'HaoYiZF / Fuhao Hardware Crafts',
                'contact_person' => 'Temu Seller (PO-076-11149563187830774)',
                'website' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=606203291790930&sku_id=89857024078818',
                'country' => 'China',
                'country_code' => 'CN',
                'lead_time_land_days' => 10,
                'lead_time_air_days' => 7,
                'shipping_method' => 'land',
                'notes' => 'Reale Vorbestellung Beleg 15.10.2026: 2x 8er Pack (16 Stk.) Gold/Schwarz Edelstahl-Schlüsselanhänger (goods_id=606203291790930, sku_id=89857024078818). EK: 1,16 € netto / 1,48 € inkl. Einfuhrabgaben.',
                'dynamic_links' => [
                    [
                        'title' => 'Temu Direkt-Nachbestellung (8er Set Gold)',
                        'url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=606203291790930&parent_order_sn=PO-076-11149563187830774&_oak_order_sn=076-11149531730550774&_oak_goods_num=2&sku_id=89857024078818'
                    ]
                ],
            ]
        );

        // 6. HÄNDLER 6: Thal-Versand / Amazon Business DE (Seelen-Pfote Hundemarken-Rohlinge)
        ProductSupplier::updateOrCreate(
            ['name' => 'Thal-Versand / Amazon Business DE'],
            [
                'company_name' => 'Thal-Versand e.K. / Amazon Business EU S.à r.l.',
                'contact_person' => 'B2B Rohlinge & Eloxal-Marken',
                'website' => 'https://www.amazon.de/s?k=Hundemarken+Rohlinge+Aluminium+Knochen+50+St%C3%BCck+Schwarz+Gravur',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => '50er / 100er Pack eloxierte Aluminium Hundemarken Knochenform (38x25 mm) inkl. Schlüsselring. EK ca. 0,28 € pro Stück bei 50er Packung.',
                'dynamic_links' => [
                    [
                        'title' => 'Amazon DE – 50x Alu-Knochen Rohlinge Schwarz',
                        'url' => 'https://www.amazon.de/s?k=Hundemarken+Rohlinge+Aluminium+Knochen+50+St%C3%BCck+Schwarz+Gravur'
                    ],
                    [
                        'title' => 'Thal-Versand Hundemarken Großhandel',
                        'url' => 'https://www.thal-versand.de/'
                    ]
                ],
            ]
        );

        // 7. HÄNDLER 7: Heavytool-Shop.de (150x Alu Schlüsselanhänger / Flaschenöffner auf Lager)
        ProductSupplier::updateOrCreate(
            ['name' => 'Heavytool-Shop.de'],
            [
                'company_name' => 'Schiebort & Shergill Trends of Trade GbR',
                'contact_person' => 'Stephan Schiebort',
                'street' => 'Heidener Straße',
                'house_number' => '70',
                'zip' => '46325',
                'city' => 'Borken',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 2861 8083448',
                'email' => 'info@heavytool.de',
                'website' => 'https://www.heavytool-shop.de/',
                'vat_id' => 'DE264984487',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => 'Rechnung RE2026-85223 vom 03.01.2026: 150 Stk. Alu-Schlüsselanhänger mit Flaschenöffner (0,29 € / Stk. = 43,99 €). Bereits im Lagerbestand!',
                'dynamic_links' => [],
            ]
        );

        // 8. HÄNDLER 8: Glücksfieber (Lübeck, DE – Edelstahl- & Ion-Plating-Laserrohlinge)
        ProductSupplier::updateOrCreate(
            ['name' => 'Glücksfieber (Lübeck)'],
            [
                'company_name' => 'Stefan Spreier & Sandra Schmidt GbR (Glücksfieber)',
                'contact_person' => 'Stefan Spreier & Sandra Schmidt',
                'street' => 'Stockholmring',
                'house_number' => '8',
                'zip' => '23560',
                'city' => 'Lübeck',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 451 4799850',
                'email' => 'kundenservice@gluecksfieber.de',
                'website' => 'https://www.gluecksfieber.de/shop/laserrohlinge/',
                'vat_id' => 'DE246566105',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => 'Deutscher Fachhändler für Edelstahl-Laserrohlinge (304/316L Chirurgenstahl & 14k Ion-Plating). Hauptprodukt: Edelstahl Laserrohling Anhänger, Herz, mit Collierschlaufe (25 x 22 x 4 mm, Öse 3 x 6 mm).',
                'dynamic_links' => [
                    [
                        'title' => 'Herz-Anhänger mit Collierschlaufe (14k vergoldet, 25x22x4 mm)',
                        'url' => 'https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html'
                    ],
                    [
                        'title' => 'Glücksfieber Laserrohlinge Katalog',
                        'url' => 'https://www.gluecksfieber.de/shop/laserrohlinge/'
                    ],
                ],
            ]
        );

        // 9. HÄNDLER 9: Print Equipment GmbH & Co. KG (Ottersweier, DE – B2B Schiefer & Laserartikel)
        ProductSupplier::updateOrCreate(
            ['name' => 'Print Equipment GmbH & Co. KG'],
            [
                'company_name' => 'Print Equipment GmbH & Co. KG',
                'contact_person' => 'Mario Panter / B2B Laser-Vertrieb',
                'street' => 'Im Stühlinger',
                'house_number' => '10',
                'zip' => '77833',
                'city' => 'Ottersweier',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 7223 28150',
                'email' => 'info@printequipment.de',
                'website' => 'https://shop.printequipment.de/',
                'vat_id' => 'DE814310164',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => 'Deutscher B2B-Großhändler (als Selbstständiger registriert unter https://shop.printequipment.de/). Fokus: Unbeschichtete Naturschieferplatten, Edelstahl-Flachmänner, Thermobecher & Holz-Laserartikel.',
                'dynamic_links' => [
                    [
                        'title' => 'Print Equipment B2B Shop (Laserartikel)',
                        'url' => 'https://shop.printequipment.de/laser/'
                    ],
                    [
                        'title' => 'Naturschiefer-Platten für Lasergravur',
                        'url' => 'https://shop.printequipment.de/laser/laserartikel/schiefer/'
                    ],
                    [
                        'title' => 'Edelstahl-Flachmänner & Becher',
                        'url' => 'https://shop.printequipment.de/laser/laserartikel/edelstahl/'
                    ],
                ],
            ]
        );
    }
}
