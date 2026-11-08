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
                        'title' => 'Temu Direkt-Nachbestellung: Seelen-Talisman (8er Set Gold, PO-076-11149563187830774)',
                        'url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=606203291790930&parent_order_sn=PO-076-11149563187830774&_oak_order_sn=076-11149531730550774&_oak_goods_num=2&sku_id=89857024078818'
                    ],
                    [
                        'title' => 'Temu Direkt-Nachbestellung: Seelen-Pfote (3x 10er Set Gold, PO-076-04964841144950774)',
                        'url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601099544143627&parent_order_sn=PO-076-04964841144950774&_oak_order_sn=076-04964799201910774&_oak_goods_num=3&_x_sessn_id=qexm6jf56e&refer_page_name=bgt_order_receipt&refer_page_id=10471_1793672371535_hlekpk8i9o&refer_page_sn=10471&is_back=1'
                    ],
                ],
            ]
        );

        // 6. HÄNDLER 6: CTHOLD (Wuxi Qiyue) - Temu & Amazon DE (Die Seelen-Pfote Hundemarke Gold + passende Gold-Spaltringe)
        ProductSupplier::where('name', 'Thal-Versand / Amazon Business DE')->delete();
        ProductSupplier::updateOrCreate(
            ['name' => 'CTHOLD (Wuxi Qiyue) - Temu & Amazon DE'],
            [
                'company_name' => 'Wuxi Qiyue Industry and Trade Co., Ltd (CTHOLD via Temu) & Amazon DE',
                'contact_person' => 'CTHOLD (PO-076-04964841144950774) / Amazon DE (028-1563466-9621923)',
                'website' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601099544143627&parent_order_sn=PO-076-04964841144950774&_oak_order_sn=076-04964799201910774&_oak_goods_num=3',
                'country' => 'China / Deutschland',
                'country_code' => 'CN',
                'lead_time_land_days' => 10,
                'lead_time_air_days' => 7,
                'shipping_method' => 'land',
                'notes' => 'Reale Bestellungen vom 03.11.2026: (1) Temu Order PO-076-04964841144950774: 3x 10er Set = 30 Stk. Edelstahl-Hundeanhänger Knochenform mit hohlem Pfoten-Durchbruch in Gold (40x22x1,8 mm, goods_id=601099544143627) für 28,59 € brutto (24,03 € netto = 0,80 € netto/Stk.). (2) Amazon DE Order 028-1563466-9621923 (16,67 € brutto): 30x JeoPoom Flache Schlüsselringe Gold (20/25/30mm, ASIN B093R9HS4Y, Teilrechnung TVtCBC8ZD-1793755138 vom 04.11.2026 von Hong Kong Tsun Yew Limited, 4,99 € brutto / 4,19 € netto = 0,14 € netto/Stk.) + 100x Nogeqi Edelstahl Spaltringe Klein Gold (6-12mm, ASIN B0H6FG92JY, 4,99 € brutto / 0,04 € netto/Stk.) + 1x LEONTOOL Mini-Spaltringzange (10,2 cm, ASIN B0C2TCMBSG, 6,69 € brutto). Komplett-EK pro Seelen-Pfote inkl. Ring-Set: 0,98 € netto.',
                'dynamic_links' => [
                    [
                        'title' => 'Temu Direkt-Nachbestellung: 3x 10er Set Seelen-Pfote Gold (Order PO-076-04964841144950774)',
                        'url' => 'https://www.temu.com/goods.html?_bg_fs=1&goods_id=601099544143627&parent_order_sn=PO-076-04964841144950774&_oak_order_sn=076-04964799201910774&_oak_goods_num=3&_x_sessn_id=qexm6jf56e&refer_page_name=bgt_order_receipt&refer_page_id=10471_1793672371535_hlekpk8i9o&refer_page_sn=10471&is_back=1'
                    ],
                    [
                        'title' => 'Amazon DE – 200x Goldene Schlüsselringe / Spaltringe Ø 20 mm (HUAZIZ, ASIN B0F9FKZK3M)',
                        'url' => 'https://www.amazon.de/dp/B0F9FKZK3M'
                    ],
                    [
                        'title' => 'Amazon DE – 30x Flache Schlüsselringe Gold Set (JeoPoom, ASIN B093R9HS4Y)',
                        'url' => 'https://www.amazon.de/dp/B093R9HS4Y'
                    ],
                    [
                        'title' => 'Amazon DE – 100x Edelstahl Spaltringe Gold klein 6–12 mm (Nogeqi, ASIN B0H6FG92JY)',
                        'url' => 'https://www.amazon.de/dp/B0H6FG92JY'
                    ],
                    [
                        'title' => 'Amazon DE – LEONTOOL Mini-Spaltringzange / Ringöffnungszange (ASIN B0C2TCMBSG)',
                        'url' => 'https://www.amazon.de/dp/B0C2TCMBSG'
                    ],
                    [
                        'title' => 'Amazon DE – WISEUP 8-tlg. Mini-Zangen-Set Schmuckherstellung (ASIN B0F38B4DMC)',
                        'url' => 'https://www.amazon.de/dp/B0F38B4DMC'
                    ],
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
                'contact_person' => 'Stefan Spreier & Sandra Schmidt (Kundennr.: 252590)',
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
                'notes' => 'Kundennr.: 252590 (Steuer-Nr. 22 285 50300). Deutscher Fachhändler für Edelstahl-Laserrohlinge & passende Edelstahl-Halsketten (14 Karat vergoldet & Ion-Plating IP). Hauptprodukt: Edelstahl Laserrohling Anhänger, Herz, mit Collierschlaufe (Art.-Nr. STA1274, 25 x 22 x 4 mm, Öse 3 x 6 mm) + passende vergoldete IP-Edelstahlketten (Art.-Nr. STA268, STA1073, STA918). Letzte Rechnung: RE2613436 (Bestellung 325015 vom 03.11.2026).',
                'dynamic_links' => [
                    [
                        'title' => 'Herz-Anhänger mit Collierschlaufe (Art. STA1274, 25x22x4 mm, Öse 3x6 mm)',
                        'url' => 'https://www.gluecksfieber.de/shop/laserrohlinge/edelstahl-laserrohling-anhaenger-herz-mit-collierschlaufe-14-karat-vergoldet-25-x-22-x-4-mm-oese-3-x-6-mm-ion-plating-ip.html'
                    ],
                    [
                        'title' => 'Passende Kette #1: Edelstahl-Ankerkette Gold IP 44+6 cm (Art. STA268, 1,5 mm)',
                        'url' => 'https://www.gluecksfieber.de/shop/edelstahl/edelstahl-gliederketten/edelstahl-gliederkette-mit-karabinerverschluss-ankerkette-goldfarben-laenge-44-cm-6-cm-verlaengerungskettchen-kettenglieder-1-5-mm-ion-plating-ip.html'
                    ],
                    [
                        'title' => 'Passende Kette #2: Edelstahl-Ankerkette Gold IP 50 cm (Art. STA1073, 1,7 mm)',
                        'url' => 'https://www.gluecksfieber.de/shop/edelstahl/edelstahl-gliederketten/edelstahl-ankerkette-halskette-mit-karabinerverschluss-18-karat-vergoldet-laenge-ca-50-cm-kettenglieder-3-5-x-1-7-x-0-4-mm-ion-plating-ip.html'
                    ],
                    [
                        'title' => 'Passende Kette #3: Edelstahl-Schlangenkette Gold 45 cm (Art. STA918, 2,2 mm)',
                        'url' => 'https://www.gluecksfieber.de/shop/edelstahl/edelstahl-gliederketten/edelstahl-schlangenkette-mit-karabinerverschluss-goldfarben-breite-2-2-mm-laenge-45-cm.html'
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

        // 10. HÄNDLER 10: Westpack A/S (Holstebro, DK – Schmuckschachteln & Großbrief-Etuis)
        ProductSupplier::updateOrCreate(
            ['name' => 'Westpack A/S'],
            [
                'company_name' => 'Westpack A/S',
                'contact_person' => 'Azizah Fitria Nada Zahra (Kundennr.: 7164015 | Klischee: L247401)',
                'street' => 'Sletten',
                'house_number' => '21',
                'zip' => '7500',
                'city' => 'Holstebro',
                'country' => 'Dänemark',
                'country_code' => 'DK',
                'phone' => '+45 97 41 23 45',
                'email' => 'afz@westpack.com',
                'website' => 'https://www.westpack.com/eur_eu_ger/',
                'vat_id' => 'DK15505908',
                'lead_time_land_days' => 4,
                'shipping_method' => 'land',
                'notes' => 'Kundennr.: 7164015 | Westpack Klischee-Nr.: L247401 (Logo-Abmessung: 60 x 11,4 mm auf 86 x 86 x 17 mm Schachtel, Logokorrektur vom 03.11.2026 durch Azizah Fitria Nada Zahra, afz@westpack.com). Order #29000069521 vom 02.11.2026: 96 Stk. Frankfurt ECO Schmuckschachtel Anhänger/Armreif (Art.-Nr. 30286060102, 86 x 86 x 17 mm, Schwarz matt inkl. eigenem Mein-Seelenfunke-Logodruck & Klischee 60000002000 zu 0,00 €). EK: 1,06 € netto / Stk. (101,76 € netto + 15,00 € Versand + 29,19 € Steuer = 145,95 € per PayPal). 17 mm flach für 1,80 € Großbrief-Versand!',
                'dynamic_links' => [
                    [
                        'title' => 'Frankfurt ECO Schmuckschachtel 86×86×17 mm (Art. 30286060102)',
                        'url' => 'https://www.westpack.com/eur_eu_ger/frankfurt-eco-jewellery-box-for-pendant-bangle-30286060102.html'
                    ],
                    [
                        'title' => 'Bestellung Order #29000069521 (96 Stk. mit Logo)',
                        'url' => 'https://www.westpack.com/eur_eu_ger/sales/order/print/order_id/485928/'
                    ],
                ],
            ]
        );

        // 11. HÄNDLER 11: IPS Karton.eu GmbH & Co. KG (Versandkartons, Schwerlast-Umkartons & Luftpolstertaschen)
        ProductSupplier::where('name', 'Karton.eu (Lausitzer Kartonagen)')->delete();
        ProductSupplier::updateOrCreate(
            ['name' => 'IPS Karton.eu GmbH & Co. KG'],
            [
                'company_name' => 'IPS Karton.eu GmbH & Co. KG',
                'contact_person' => 'Bestellabteilung (Kundennr.: K523045)',
                'street' => 'Tuchmacherallee',
                'house_number' => '6',
                'zip' => '03130',
                'city' => 'Spremberg',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 3563 348030',
                'email' => 'info@karton.eu',
                'website' => 'https://www.karton.eu/',
                'vat_id' => 'DE288964141',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => 'Kundennr.: K523045 (St.-Nr.: 056/156/00190). Vollständiger Verpackungsbestand auf Lager: 1) Rechnung 26001745 (04.01.2026): 240x Buchverpackung 284x244x0-70 mm (Art.-Nr. 6227, 140g, 0,60 € netto) für Einzel-Trophäen + 400x Luftpolster-Versandtaschen (15g, 0,14 € netto). 2) Rechnung 26169398 / Bestellung B2075689 / Auftrag A2416277 (03.11.2026, 273,27 € brutto, 67,40 kg): 140x Universal-/Bundle- & Weizenglas-Karton 300x230x150 mm einwellig (Art.-Nr. 2633, 180g, 0,50 € netto) + 20x Schwerlastkarton 600x400x200 mm 2-wellig BC-Welle (Art.-Nr. 165, 750g, 2,78 € netto, für 4–10 Trophäen) + 20x Schwerlastkarton 600x400x400 mm 2-wellig BC-Welle (Art.-Nr. 069, 1.040g, 3,24 € netto, für 11–20 Trophäen) + 36 Rollen Leise abrollendes BOPP-Klebeband braun (Art.-Nr. 128, 170g, 1,09 € netto).',
                'dynamic_links' => [
                    [
                        'title' => 'Einzel-Trophäe (240×): Buchverpackung 284×244×0–70 mm (Art.-Nr. 6227, 0,60 € netto)',
                        'url' => 'https://www.karton.eu/284x244x0-70-mm-Buchverpackung-DHL-Kleinpaket'
                    ],
                    [
                        'title' => 'Universal-Bundle & Weizenglas (140×): 300×230×150 mm 1-wellig (Art.-Nr. 2633, 0,50 € netto)',
                        'url' => 'https://www.karton.eu/300x230x150-mm-einwellige-Kartons'
                    ],
                    [
                        'title' => 'Mittlere Großbestellung 4–10 Trophäen (20×): 600×400×200 mm 2-wellig (Art.-Nr. 165, 2,78 € netto)',
                        'url' => 'https://www.karton.eu/600x400x200-mm-zweiwellige-Kartons-Aussenmass'
                    ],
                    [
                        'title' => 'Großbestellung 11–20 Trophäen/Paket (20×): 600×400×400 mm 2-wellig (Art.-Nr. 069, 3,24 € netto)',
                        'url' => 'https://www.karton.eu/?qs=600x400x400'
                    ],
                    [
                        'title' => 'Klebeband (36 Rollen): Leise abrollendes BOPP-Klebeband braun (Art.-Nr. 128, 1,09 € netto)',
                        'url' => 'https://www.karton.eu/?qs=128'
                    ],
                ],
            ]
        );

        // 12. HÄNDLER 12: Silberketten-Store.de (Udo Hermann – 333/585 Echtgold & 925 Sterling Silber Rohlinge)
        ProductSupplier::updateOrCreate(
            ['name' => 'Silberketten-Store.de (Udo Hermann)'],
            [
                'company_name' => 'Silberketten-Store / Udo Hermann',
                'contact_person' => 'Udo Hermann',
                'street' => 'Hauptstraße',
                'house_number' => '45',
                'zip' => '55743',
                'city' => 'Idar-Oberstein',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'email' => 'info@silberketten-store.de',
                'website' => 'https://www.silberketten-store.de/',
                'lead_time_land_days' => 3,
                'shipping_method' => 'land',
                'notes' => 'Deutscher Fachhändler für 333er/585er Echtgold und 925er Sterling Silber (Einzelabruf ab 1 Stk. auf Kundenbestellung ohne Mindestbestellwert). Wichtig bei Bestellung: Dropdown „Gravur“ immer leer lassen („Bitte wählen Sie“), damit der blanke, gestempelte Rohling ohne 5,00 € Gravur-Aufpreis geliefert wird.',
                'dynamic_links' => [
                    [
                        'title' => 'Herz-Anhänger: 585er Echtgold (13×14 mm, 0,80 g, Art.-Nr. 235559.400, 134,00 € brutto)',
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Kettenanhaenger/Gravuranhaenger-Herz-235559-400-585-Gold-10704'
                    ],
                    [
                        'title' => 'Passende Kette im Set: 333er Echtgold Panzerkette (0,8 mm, 69,00 € brutto)',
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Ketten/Panzerketten/Panzerkette-0-8mm-333-Gold'
                    ],
                    [
                        'title' => 'Gravurarmband Figaro 4,0 mm – 925 Sterling Silber (Platte 40×4 mm, ab 22,90 € brutto)',
                        'url' => 'https://www.silberketten-store.de/SILBERSCHMUCK/Gravurartikel/Gravurarmbaender/Figaro-Gravurarmband-4mm'
                    ],
                    [
                        'title' => 'Panzer-Gravurarmband 5,5 mm – 925 Sterling Silber (Platte 40×5,5 mm, ab 49,00 € brutto)',
                        'url' => 'https://www.silberketten-store.de/SILBERSCHMUCK/Gravurartikel/Gravurarmbaender/Gravurarmband-5-5mm'
                    ],
                    [
                        'title' => 'Kinder-/Tauf-Gravurarmband Panzer 1,8 mm – 333 Echtgold (Platte 25×5 mm, 179,00 € brutto)',
                        'url' => 'https://www.silberketten-store.de/SILBERSCHMUCK/Gravurartikel/Kindergravurarmbaender/Kinder-Gravurarmband-Panzer-1-8mm-333-Gold'
                    ],
                    [
                        'title' => 'Kinder-/Tauf-Gravurarmband Figaro 1,5 mm – 585 Echtgold (Platte 20×5 mm, 212,00 € brutto)',
                        'url' => 'https://www.silberketten-store.de/GOLDSCHMUCK/Gravurartikel/Kindergravurarmbaender/Kinder-Gravurarmband-Figaro-1-5mm-585-Gold'
                    ],
                ],
            ]
        );

        // 13. HÄNDLER 13: Friedrich Binder GmbH & Co. KG (FBM – Deutscher Ketten- & ID-Armband-Hersteller seit 1910)
        ProductSupplier::updateOrCreate(
            ['name' => 'Friedrich Binder GmbH & Co. KG (FBM)'],
            [
                'company_name' => 'Friedrich Binder GmbH & Co. KG (FBM)',
                'contact_person' => 'B2B-Vertrieb & Webshop (Gewerbenachweis)',
                'street' => 'Wagnerstraße',
                'house_number' => '34',
                'zip' => '71297',
                'city' => 'Mönsheim (bei Pforzheim)',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 7044 9210',
                'email' => 'info@fbm.de',
                'website' => 'https://www.fbm-chains.com/schmuck/id-armbaender.php',
                'vat_id' => 'DE146019897',
                'lead_time_land_days' => 3,
                'shipping_method' => 'land',
                'notes' => 'Deutscher Traditionshersteller („Made in Germany“ seit 1910, RJC-zertifiziert, 100 % recyceltes Gold & Silber). Produziert echte ungravierte ID-Gravurarmbänder in 925 Sterling Silber, 333 Gold, 585 Gold und 750 Gold direkt ab Werk für Gewerbekunden (eigener B2B-Webshop).',
                'dynamic_links' => [
                    [
                        'title' => 'FBM Direkt-Bestellung: ID-Armband Figaro 2,1 mm mit Herz (925 Silber, 18,5 cm, P001.53062HERZ.5.18.5)',
                        'url' => 'https://shop.fbm-chains.com/id-armband-figaro-diamantiert-2.1mm/P001.53062HERZ.5.18.5'
                    ],
                    [
                        'title' => 'FBM Direkt-Bestellung: ID-Armband Figaro 2,1 mm mit Herz (925 Gelb vergoldet, 18,5 cm, P020.53062HERZ.5.18.5)',
                        'url' => 'https://shop.fbm-chains.com/id-armband-figaro-diamantiert-2.1mm/P020.53062HERZ.5.18.5'
                    ],
                    [
                        'title' => 'FBM Direkt-Bestellung: ID-Armband Figaro 2,1 mm (333 Echtgold, 18,5 cm, P003.53062.5.18.5)',
                        'url' => 'https://shop.fbm-chains.com/id-armband-figaro-diamantiert-2.1mm/P003.53062.5.18.5'
                    ],
                    [
                        'title' => 'FBM ID-Armbänder Gesamtkatalog (Shop)',
                        'url' => 'https://shop.fbm-chains.com/armbaender/rubriken/id-armbaender/'
                    ],
                ],
            ]
        );

        // 14. HÄNDLER 14: Max Fröhlich GmbH (Deutscher B2B-Schmuckgroßhandel für Identitäts-Armbänder Gold & Silber)
        ProductSupplier::updateOrCreate(
            ['name' => 'Max Fröhlich GmbH (B2B Schmuckgroßhandel)'],
            [
                'company_name' => 'Max Fröhlich GmbH',
                'contact_person' => 'B2B Großhandel (Gewerbe-Freischaltung)',
                'street' => 'Rellinghauser Straße',
                'house_number' => '334',
                'zip' => '45136',
                'city' => 'Essen',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'phone' => '+49 201 894280',
                'email' => 'info@maxfroehlich.de',
                'website' => 'https://b2b-shop.maxfroehlich.de/standard-schmuck/gold-silberketten-und-armbaender/identitaets-armbaender',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => 'Einer der größten deutschen B2B-Schmuckgroßhändler mit eigener Rubrik „Identitäts-Armbänder“ (blanko Gravurarmbänder in 925 Silber rhodiniert/vergoldet sowie 333er & 585er Echtgold zum echten Großhandels-EK).',
                'dynamic_links' => [
                    [
                        'title' => 'Max Fröhlich B2B: Identitäts-/Gravur-Armbänder (Gold & 925 Silber)',
                        'url' => 'https://b2b-shop.maxfroehlich.de/standard-schmuck/gold-silberketten-und-armbaender/identitaets-armbaender'
                    ],
                ],
            ]
        );

        // 15. HÄNDLER 15: MATERIA Schmuck by Matthias Wagner (Sofort-Einzelabruf ab 1 Stk. ohne B2B-Mindestbestellwert)
        ProductSupplier::updateOrCreate(
            ['name' => 'MATERIA Schmuck (Matthias Wagner)'],
            [
                'company_name' => 'Matthias Wagner (MATERIA Schmuck)',
                'contact_person' => 'Matthias Wagner',
                'street' => 'Rudolf-Breitscheid-Straße',
                'house_number' => '19',
                'zip' => '07586',
                'city' => 'Bad Köstritz',
                'country' => 'Deutschland',
                'country_code' => 'DE',
                'email' => 'info@materia-schmuck.de',
                'website' => 'https://www.materia-schmuck.de/',
                'lead_time_land_days' => 2,
                'shipping_method' => 'land',
                'notes' => 'Deutscher Fachhändler für sehr massive, rhodinierte (anlaufgeschützte) 925 Sterling Silber Panzer-Gravurarmbänder („Made in Germany“). Jedes Armband ist direkt ab 1 Stück in der Variante „Ohne Gravur“ als Blanko-Rohling zum Lasern bestellbar. Zusätzlich Kleckow B2B Pforzheim (https://kleckow.de/schmuck-b2b-shop/) und OROVIVO (https://www.orovivo.de/de_DE/c/armbaender/armbandart/armbaender-mit-gravur/) als weitere Bezugsquellen.',
                'dynamic_links' => [
                    [
                        'title' => 'MATERIA 925 Silber Panzer-Gravurarmband (rhodiniert, 19–23 cm, Variante „Ohne Gravur“, 121,95 € brutto)',
                        'url' => 'https://www.materia-schmuck.de/gravur-armband-herren-panzer-kette/a-10553/'
                    ],
                    [
                        'title' => 'MATERIA Ultra-Massiv 44g Herren-Gravurarmband 925 Silber (Platte 40×9×2 mm, „Ohne Gravur“, 269,95 € brutto)',
                        'url' => 'https://www.materia-schmuck.de/gravur-armband-maenner-silber-925/a-11492/'
                    ],
                    [
                        'title' => 'Kleckow GmbH Pforzheim – B2B Echtschmuck-Großhandel (Same-Day-Versand bis 12 Uhr)',
                        'url' => 'https://kleckow.de/schmuck-b2b-shop/'
                    ],
                    [
                        'title' => 'OROVIVO – 375er/585er Echtgold & 925er Silber Gravurarmbänder (blanko bestellbar)',
                        'url' => 'https://www.orovivo.de/de_DE/c/armbaender/armbandart/armbaender-mit-gravur/'
                    ],
                ],
            ]
        );
    }
}
