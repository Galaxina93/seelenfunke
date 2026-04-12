<?php

namespace Database\Seeders;

use App\Models\System\SystemMapNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SystemApiNodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $apis = [
            [
                'label' => 'DHL API',
                'description' => 'Paketlabel & Tracking',
                'icon' => 'globe', // fallback icon
                'type' => 'default',
                'status' => 'active',
                'link' => 'https://api.dhl.com',
                'map_id' => 'erp',
            ],
            [
                'label' => 'finAPI',
                'description' => 'Bank-Synchronisation',
                'icon' => 'globe',
                'type' => 'default',
                'status' => 'active',
                'link' => 'https://live.finapi.io',
                'map_id' => 'erp',
            ],
            [
                'label' => 'Mittwald AI',
                'description' => 'LLM KI-Modelle Server',
                'icon' => 'globe',
                'type' => 'default',
                'status' => 'active',
                'link' => 'https://llm.aihosting.mittwald.de',
                'map_id' => 'ai',
            ],
            [
                'label' => 'Google Gemini',
                'description' => 'Flash 1.5 KI-Modell',
                'icon' => 'globe',
                'type' => 'default',
                'status' => 'active',
                'link' => 'https://generativelanguage.googleapis.com',
                'map_id' => 'ai',
            ],
            [
                'label' => 'Google Places',
                'description' => 'Orts- & Rezensions-Daten',
                'icon' => 'globe',
                'type' => 'default',
                'status' => 'active',
                'link' => 'https://maps.googleapis.com',
                'map_id' => 'erp',
            ],
            [
                'label' => 'Elster (ERiC)',
                'description' => 'Finanzamt Schnittstelle',
                'icon' => 'globe',
                'type' => 'default',
                'status' => 'active',
                'link' => 'https://www.elster.de/elsterweb/serverstatus_rss.xml',
                'map_id' => 'erp',
            ],
            [
                'label' => 'ScraperAPI',
                'description' => 'Proxy-Service',
                'icon' => 'globe',
                'type' => 'default',
                'status' => 'active',
                'link' => 'http://api.scraperapi.com',
                'map_id' => 'ai',
            ],
        ];

        // Ensure nodes exist
        foreach ($apis as $idx => $api) {
            $exists = SystemMapNode::where('label', $api['label'])->orWhere('link', $api['link'])->first();
            
            if (!$exists) {
                SystemMapNode::create([
                    'id' => Str::uuid(),
                    'map_id' => $api['map_id'],
                    'label' => $api['label'],
                    'description' => $api['description'],
                    'icon' => $api['icon'],
                    'type' => $api['type'],
                    'status' => $api['status'],
                    'link' => $api['link'],
                    // Random positions for new nodes to not clash exactly in top left
                    'pos_x' => 100 + ($idx * 50),
                    'pos_y' => 100 + ($idx * 30),
                ]);
            }
        }
    }
}
