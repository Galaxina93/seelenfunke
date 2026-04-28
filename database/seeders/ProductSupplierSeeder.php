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
                'address' => 'China',
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
                'address' => "Xipingxianyishugongyipin Co., Ltd.\nBaiyuan Avenue, Henan New Packaging Materials Industrial Park, Commercial Zone D, No. 1-83\nXiping County 463900\nHenan Province, Zhumadian City\nChina",
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
                'address' => 'Amazon Händler',
                'lead_time_land_days' => 5,
                'shipping_method' => 'land',
                'dynamic_links' => [],
            ]
        );
    }
}
