<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Marketing\MarketingLinktree;
use App\Models\Marketing\MarketingTrackingLink;
use Illuminate\Support\Facades\Schema;

class MarketingLinktreeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $links = [
            [
                'title' => 'Zum Shop (Manufaktur)',
                'url' => '/shop',
                'icon' => 'shopping-bag',
                'type' => 'highlight',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Jetzt Neuheiten entdecken',
                'url' => '/shop',
                'icon' => 'sparkles',
                'type' => 'standard',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'TikTok',
                'url' => 'https://tiktok.com/@mein_seelenfunke',
                'icon' => 'tiktok',
                'type' => 'standard',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Instagram',
                'url' => 'https://instagram.com/mein_seelenfunke',
                'icon' => 'instagram',
                'type' => 'standard',
                'sort_order' => 4,
                'is_active' => true,
            ],
            [
                'title' => 'Kontakt & B2B Anfragen',
                'url' => '/kontakt',
                'icon' => 'envelope',
                'type' => 'standard',
                'sort_order' => 5,
                'is_active' => true,
            ],
        ];

        foreach ($links as $link) {
            MarketingLinktree::updateOrCreate(
                ['title' => $link['title']],
                $link
            );
        }

        if (Schema::hasTable('marketing_tracking_links')) {
            $trackingLinks = [
                [
                    'name' => 'Instagram Bio-Link',
                    'slug' => 'instagram-bio',
                    'target_url' => '/shop',
                    'channel' => 'social',
                    'utm_source' => 'instagram',
                    'utm_medium' => 'bio',
                    'utm_campaign' => 'profil_link',
                    'is_active' => true,
                ],
                [
                    'name' => 'TikTok Bio-Link',
                    'slug' => 'tiktok-bio',
                    'target_url' => '/shop',
                    'channel' => 'social',
                    'utm_source' => 'tiktok',
                    'utm_medium' => 'bio',
                    'utm_campaign' => 'profil_link',
                    'is_active' => true,
                ],
                [
                    'name' => 'WhatsApp Status / Teilen',
                    'slug' => 'whatsapp',
                    'target_url' => '/shop',
                    'channel' => 'social',
                    'utm_source' => 'whatsapp',
                    'utm_medium' => 'social',
                    'utm_campaign' => 'whatsapp_share',
                    'is_active' => true,
                ],
                [
                    'name' => 'Newsletter Hauptaktion',
                    'slug' => 'newsletter',
                    'target_url' => '/shop',
                    'channel' => 'campaign',
                    'utm_source' => 'newsletter',
                    'utm_medium' => 'email',
                    'utm_campaign' => 'newsletter_hauptlink',
                    'is_active' => true,
                ],
            ];

            foreach ($trackingLinks as $trk) {
                MarketingTrackingLink::updateOrCreate(
                    ['slug' => $trk['slug']],
                    $trk
                );
            }
        }
    }
}
