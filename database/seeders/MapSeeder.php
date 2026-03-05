<?php

namespace Database\Seeders;

use App\Models\Map\MapEdge;
use App\Models\Map\MapNode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MapSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        MapEdge::truncate();
        MapNode::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. KNOTEN DEFINIEREN (Optimiertes Array mit neuem Hub-and-Spoke Layout)
        $nodesData = [
            // Zentrum
            'core'     => ['label' => 'Mein-Seelenfunke', 'desc' => 'Das Herzstück / ERP', 'icon' => 'sparkles', 'type' => 'core', 'status' => 'active', 'link' => 'https://mein-seelenfunke.de', 'x' => 50.00, 'y' => 50.00, 'panel' => 'settings'],

            // Oben (APIs & Steuern)
            'google'   => ['label' => 'Google API', 'desc' => 'Maps, Fonts, Auth', 'icon' => 'google', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.cloud.google.com', 'x' => 50.00, 'y' => 15.00, 'panel' => ''],
            'datev'    => ['label' => 'DATEV API', 'desc' => 'Steuer Export', 'icon' => 'datev', 'type' => 'api', 'status' => 'inactive', 'link' => 'https://datev.de', 'x' => 70.00, 'y' => 20.00, 'panel' => 'finances'],

            // Rechts (Steuern & Logistik)
            'eric'     => ['label' => 'ERiC (Elster)', 'desc' => 'Finanzamt UStVA API', 'icon' => 'building-library', 'type' => 'finance', 'status' => 'active', 'link' => 'https://www.elster.de', 'x' => 85.00, 'y' => 35.00, 'panel' => 'finances'],
            'dhl'      => ['label' => 'DHL API', 'desc' => 'Automatischer Label-Druck', 'icon' => 'dhl', 'type' => 'api', 'status' => 'planned', 'link' => 'https://developer.dhl.com', 'x' => 85.00, 'y' => 65.00, 'panel' => 'shipping'],

            // Unten Rechts (Finanzen)
            'stripe'   => ['label' => 'Stripe API', 'desc' => 'Payments & Wallets', 'icon' => 'stripe', 'type' => 'finance', 'status' => 'active', 'link' => 'https://dashboard.stripe.com', 'x' => 65.00, 'y' => 85.00, 'panel' => 'finances'],
            'finom'    => ['label' => 'Bank Finom', 'desc' => 'Geschäftskonto', 'icon' => 'finom', 'type' => 'finance', 'status' => 'active', 'link' => 'https://finom.co', 'x' => 85.00, 'y' => 90.00, 'panel' => 'finances'], // Finom hängt logisch unten rechts an Stripe

            // Unten Links (Infrastruktur)
            'mittwald' => ['label' => 'Mittwald', 'desc' => 'Server & Hosting', 'icon' => 'mittwald', 'type' => 'api', 'status' => 'active', 'link' => 'https://mittwald.de', 'x' => 20.00, 'y' => 75.00, 'panel' => ''],

            // Links & Oben Links (Vertrieb & Apps)
            'firebase' => ['label' => 'Firebase API', 'desc' => 'Push Notifications', 'icon' => 'firebase', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.firebase.google.com', 'x' => 15.00, 'y' => 50.00, 'panel' => 'api_logs'],
            'etsy'     => ['label' => 'Etsy API', 'desc' => 'Marktplatz Sync', 'icon' => 'etsy', 'type' => 'sales', 'status' => 'active', 'link' => 'https://etsy.com', 'x' => 20.00, 'y' => 25.00, 'panel' => 'orders'],
        ];

        $nodes = [];
        foreach ($nodesData as $key => $data) {
            $nodes[$key] = MapNode::create([
                'id'            => Str::uuid(),
                'label'         => $data['label'],
                'description'   => $data['desc'],
                'icon'          => $data['icon'],
                'type'          => $data['type'],
                'status'        => $data['status'],
                'link'          => $data['link'],
                'pos_x'         => $data['x'],
                'pos_y'         => $data['y'],
                'component_key' => $data['panel'],
            ]);
        }

        // 2. VERBINDUNGEN DEFINIEREN
        $edgesData = [
            ['source' => 'core', 'target' => 'etsy', 'label' => 'Sync API', 'desc' => 'Importiert Etsy JSON zu internen Orders', 'status' => 'active'],
            ['source' => 'stripe', 'target' => 'core', 'label' => 'Webhooks', 'desc' => 'Bestätigt Zahlungen in Echtzeit', 'status' => 'active'],
            ['source' => 'stripe', 'target' => 'finom', 'label' => 'Payouts', 'desc' => 'Tägliche Payouts von Stripe an Finom', 'status' => 'active'],
            ['source' => 'core', 'target' => 'datev', 'label' => 'UStVA ZIP', 'desc' => 'Generiert DATEV-CSV', 'status' => 'inactive'],
            ['source' => 'core', 'target' => 'eric', 'label' => 'XML Transfer', 'desc' => 'Native UStVA Direktübertragung ans Finanzamt', 'status' => 'active'],
            ['source' => 'mittwald', 'target' => 'core', 'label' => 'Hosting', 'desc' => 'Ubuntu Space für Laravel Applikation', 'status' => 'active'],
            ['source' => 'core', 'target' => 'firebase', 'label' => 'FCM Tokens', 'desc' => 'Sendet Pushes über die App', 'status' => 'active'],
            ['source' => 'core', 'target' => 'google', 'label' => 'OAuth', 'desc' => 'Social Login Registrierung', 'status' => 'active'],
            ['source' => 'core', 'target' => 'dhl', 'label' => 'Labels', 'desc' => 'Erzeugt PDF Versandmarken', 'status' => 'planned'],
        ];

        foreach ($edgesData as $edge) {
            MapEdge::create([
                'id'          => Str::uuid(),
                'source_id'   => $nodes[$edge['source']]->id,
                'target_id'   => $nodes[$edge['target']]->id,
                'label'       => $edge['label'],
                'description' => $edge['desc'],
                'status'      => $edge['status'],
            ]);
        }
    }
}
