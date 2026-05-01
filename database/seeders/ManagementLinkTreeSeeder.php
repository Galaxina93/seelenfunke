<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Management\ManagementLinktree;

class ManagementLinkTreeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $links = [
            [
                'title' => 'Instagram',
                'url' => 'https://www.instagram.com/Mein_Seelenfunke/',
                'icon' => 'camera', // Example icon, could also be 'photo' or 'heart'
                'type' => 'highlight',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'TikTok',
                'url' => 'https://www.tiktok.com/@mein_seelenfunke',
                'icon' => 'video-camera', // Example icon, heroicon-o-video-camera
                'type' => 'standard',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'YouTube',
                'url' => 'https://www.youtube.com/@Mein-Seelenfunke',
                'icon' => 'play-circle', // Example icon, heroicon-o-play-circle
                'type' => 'standard',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'title' => 'Zum Shop',
                'url' => route('shop'),
                'icon' => 'shopping-bag', // Example icon
                'type' => 'secure',
                'is_active' => true,
                'sort_order' => 4,
            ]
        ];

        foreach ($links as $link) {
            ManagementLinktree::firstOrCreate(['url' => $link['url']], $link);
        }

        // Set default profile image if none exists
        \App\Models\System\SystemSetting::firstOrCreate(
            ['key' => 'linktree_profile_image'],
            ['value' => '/shop/projekt/about/gruender-profil.webp']
        );
    }
}
