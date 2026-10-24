<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Management\ManagementLinktree;
use App\Models\Management\ManagementTrackingLink;

class ManagementLinkTreeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $shopUrl = rtrim((string) (config('app.url') ?: url('/')), '/') . '/shop';

        $links = [
            [
                'title' => 'Instagram',
                'url' => 'https://www.instagram.com/Mein_Seelenfunke/',
                'icon' => 'camera',
                'type' => 'highlight',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'TikTok',
                'url' => 'https://www.tiktok.com/@mein_seelenfunke',
                'icon' => 'video-camera',
                'type' => 'standard',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'YouTube',
                'url' => 'https://www.youtube.com/@Mein-Seelenfunke',
                'icon' => 'play-circle',
                'type' => 'standard',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Zum Shop',
                'url' => $shopUrl,
                'icon' => 'shopping-bag',
                'type' => 'standard',
                'is_active' => true,
                'sort_order' => 4,
            ],
        ];

        foreach ($links as $link) {
            ManagementLinktree::updateOrCreate(['title' => $link['title']], $link);
        }

        if (Schema::hasTable('management_tracking_links')) {
            $defaultTrackingLinks = [
                [
                    'name' => 'Instagram Bio',
                    'slug' => 'instagram-bio',
                    'target_url' => '/shop',
                    'channel' => 'social',
                    'utm_source' => 'instagram',
                    'utm_medium' => 'bio',
                    'utm_campaign' => 'profil_linktree',
                    'is_active' => true,
                ],
                [
                    'name' => 'TikTok Profil',
                    'slug' => 'tiktok-bio',
                    'target_url' => '/shop',
                    'channel' => 'social',
                    'utm_source' => 'tiktok',
                    'utm_medium' => 'bio',
                    'utm_campaign' => 'profil_linktree',
                    'is_active' => true,
                ],
            ];

            foreach ($defaultTrackingLinks as $trk) {
                ManagementTrackingLink::firstOrCreate(['slug' => $trk['slug']], $trk);
            }
        }

        // Set default profile image if none exists
        \App\Models\System\SystemSetting::firstOrCreate(
            ['key' => 'linktree_profile_image'],
            ['value' => '/shop/projekt/about/gruender-profil.webp']
        );
    }
}
