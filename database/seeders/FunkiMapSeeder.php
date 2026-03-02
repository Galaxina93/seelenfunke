<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Funki\FunkiMapNode;
use App\Models\Funki\FunkiMapEdge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FunkiMapSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        FunkiMapEdge::truncate();
        FunkiMapNode::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1. KNOTEN DEFINIEREN (Optimiertes Array)
        $nodesData = [
            'core'     => ['label' => 'Mein-Seelenfunke', 'desc' => 'Das Herzstück / ERP', 'icon' => 'sparkles', 'type' => 'core', 'status' => 'active', 'link' => 'https://mein-seelenfunke.de', 'x' => 47.76, 'y' => 51.70, 'panel' => 'settings'],
            'etsy'     => ['label' => 'Etsy API', 'desc' => 'Marktplatz Sync', 'icon' => 'etsy', 'type' => 'sales', 'status' => 'active', 'link' => 'https://etsy.com', 'x' => 36.97, 'y' => 7.03, 'panel' => 'orders'],
            'stripe'   => ['label' => 'Stripe API', 'desc' => 'Payments & Wallets', 'icon' => 'stripe', 'type' => 'finance', 'status' => 'active', 'link' => 'https://dashboard.stripe.com', 'x' => 93.80, 'y' => 55.05, 'panel' => 'finances'],
            'finom'    => ['label' => 'Bank Finom', 'desc' => 'Geschäftskonto', 'icon' => 'finom', 'type' => 'finance', 'status' => 'active', 'link' => 'https://finom.co', 'x' => 87.60, 'y' => 75.14, 'panel' => 'finances'],
            'datev'    => ['label' => 'DATEV API', 'desc' => 'Steuer Export', 'icon' => 'datev', 'type' => 'api', 'status' => 'inactive', 'link' => 'https://datev.de', 'x' => 61.36, 'y' => 5.72, 'panel' => 'finances'],
            'eric'     => ['label' => 'ERiC (Elster)', 'desc' => 'Finanzamt UStVA API', 'icon' => 'building-library', 'type' => 'finance', 'status' => 'active', 'link' => 'https://www.elster.de', 'x' => 75.00, 'y' => 5.00, 'panel' => 'finances'], // NEU
            'mittwald' => ['label' => 'Mittwald', 'desc' => 'Server & Hosting', 'icon' => 'mittwald', 'type' => 'api', 'status' => 'active', 'link' => 'https://mittwald.de', 'x' => 21.28, 'y' => 33.30, 'panel' => ''],
            'firebase' => ['label' => 'Firebase API', 'desc' => 'Push Notifications', 'icon' => 'firebase', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.firebase.google.com', 'x' => 85.56, 'y' => 26.61, 'panel' => 'api_logs'],
            'google'   => ['label' => 'Google API', 'desc' => 'Maps, Fonts, Auth', 'icon' => 'google', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.cloud.google.com', 'x' => 77.59, 'y' => 19.01, 'panel' => ''],
            'dhl'      => ['label' => 'DHL API', 'desc' => 'Automatischer Label-Druck', 'icon' => 'dhl', 'type' => 'api', 'status' => 'planned', 'link' => 'https://developer.dhl.com', 'x' => 69.15, 'y' => 12.17, 'panel' => 'shipping'],
        ];

        $nodes = [];
        foreach ($nodesData as $key => $data) {
            $nodes[$key] = FunkiMapNode::create([
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
            ['source' => 'core', 'target' => 'eric', 'label' => 'XML Transfer', 'desc' => 'Native UStVA Direktübertragung ans Finanzamt', 'status' => 'active'], // NEU
            ['source' => 'mittwald', 'target' => 'core', 'label' => 'Hosting', 'desc' => 'Ubuntu Space für Laravel Applikation', 'status' => 'active'],
            ['source' => 'core', 'target' => 'firebase', 'label' => 'FCM Tokens', 'desc' => 'Sendet Pushes über die App', 'status' => 'active'],
            ['source' => 'core', 'target' => 'google', 'label' => 'OAuth', 'desc' => 'Social Login Registrierung', 'status' => 'active'],
            ['source' => 'core', 'target' => 'dhl', 'label' => 'Labels', 'desc' => 'Erzeugt PDF Versandmarken', 'status' => 'planned'],
        ];

        foreach ($edgesData as $edge) {
            FunkiMapEdge::create([
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
