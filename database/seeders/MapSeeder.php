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

        // 1. KNOTEN DEFINIEREN: ERP-ÖKOSYSTEM
        $erpNodesData = [
            'core'     => ['label' => 'Mein-Seelenfunke', 'desc' => 'Das Herzstück / ERP', 'icon' => 'sparkles', 'type' => 'core', 'status' => 'active', 'link' => 'https://mein-seelenfunke.de', 'x' => 50.00, 'y' => 50.00, 'panel' => 'settings'],
            'google'   => ['label' => 'Google API', 'desc' => 'Maps, Fonts, Auth', 'icon' => 'google', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.cloud.google.com', 'x' => 50.00, 'y' => 15.00, 'panel' => ''],
            'datev'    => ['label' => 'DATEV API', 'desc' => 'Steuer Export', 'icon' => 'datev', 'type' => 'api', 'status' => 'inactive', 'link' => 'https://datev.de', 'x' => 70.00, 'y' => 20.00, 'panel' => 'finances'],
            'eric'     => ['label' => 'ERiC (Elster)', 'desc' => 'Finanzamt UStVA API', 'icon' => 'building-library', 'type' => 'finance', 'status' => 'active', 'link' => 'https://www.elster.de', 'x' => 85.00, 'y' => 35.00, 'panel' => 'finances'],
            'dhl'      => ['label' => 'DHL API', 'desc' => 'Automatischer Label-Druck', 'icon' => 'dhl', 'type' => 'api', 'status' => 'planned', 'link' => 'https://developer.dhl.com', 'x' => 85.00, 'y' => 65.00, 'panel' => 'shipping'],
            'stripe'   => ['label' => 'Stripe API', 'desc' => 'Payments & Wallets', 'icon' => 'stripe', 'type' => 'finance', 'status' => 'active', 'link' => 'https://dashboard.stripe.com', 'x' => 65.00, 'y' => 85.00, 'panel' => 'finances'],
            'finom'    => ['label' => 'Bank Finom', 'desc' => 'Geschäftskonto', 'icon' => 'finom', 'type' => 'finance', 'status' => 'active', 'link' => 'https://finom.co', 'x' => 85.00, 'y' => 90.00, 'panel' => 'finances'],
            'mittwald' => ['label' => 'Mittwald', 'desc' => 'Server & Hosting', 'icon' => 'mittwald', 'type' => 'api', 'status' => 'active', 'link' => 'https://mittwald.de', 'x' => 20.00, 'y' => 75.00, 'panel' => ''],
            'firebase' => ['label' => 'Firebase API', 'desc' => 'Push Notifications', 'icon' => 'firebase', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.firebase.google.com', 'x' => 15.00, 'y' => 50.00, 'panel' => 'api_logs'],
            'etsy'     => ['label' => 'Etsy API', 'desc' => 'Marktplatz Sync', 'icon' => 'etsy', 'type' => 'sales', 'status' => 'active', 'link' => 'https://etsy.com', 'x' => 20.00, 'y' => 25.00, 'panel' => 'orders'],
        ];

        // 2. KNOTEN DEFINIEREN: KI-ARCHITEKTUR
        $aiNodesData = [
            'user'     => ['label' => 'KI Lauscht...', 'desc' => 'Wake Word & Mic Input', 'icon' => 'device-phone-mobile', 'type' => 'default', 'status' => 'active', 'link' => '', 'x' => 10.00, 'y' => 50.00, 'panel' => ''],
            'js'       => ['label' => 'Spracheingabe via Interface', 'desc' => 'Web Speech API / Alpine.js', 'icon' => 'globe-alt', 'type' => 'api', 'status' => 'active', 'link' => '', 'x' => 25.00, 'y' => 50.00, 'panel' => ''],
            'livewire' => ['label' => 'FunkiraChat.php', 'desc' => 'Livewire Component Base', 'icon' => 'bolt', 'type' => 'core', 'status' => 'active', 'link' => '', 'x' => 45.00, 'y' => 50.00, 'panel' => ''],
            'agent'    => ['label' => 'MittwaldAgent.php', 'desc' => 'Prompt System Assembler', 'icon' => 'sparkles', 'type' => 'finance', 'status' => 'active', 'link' => '', 'x' => 65.00, 'y' => 50.00, 'panel' => ''],
            'llm'      => ['label' => 'LLaMA / GPT', 'desc' => 'Mittwald AI Proxy', 'icon' => 'cpu-chip', 'type' => 'api', 'status' => 'active', 'link' => 'https://mittwald.de', 'x' => 85.00, 'y' => 25.00, 'panel' => ''],
            'registry' => ['label' => 'AIFunctionsRegistry.php', 'desc' => 'Ausgeführte Werkzeuge', 'icon' => 'wrench-screwdriver', 'type' => 'sales', 'status' => 'active', 'link' => '', 'x' => 85.00, 'y' => 75.00, 'panel' => ''],
            'db'       => ['label' => 'DB & Models', 'desc' => 'System Response Payload', 'icon' => 'circle-stack', 'type' => 'database', 'status' => 'active', 'link' => '', 'x' => 85.00, 'y' => 95.00, 'panel' => ''],
        ];

        $allNodes = [];

        foreach (['erp' => $erpNodesData, 'ai' => $aiNodesData] as $mapId => $nodesGroup) {
            foreach ($nodesGroup as $key => $data) {
                $allNodes[$mapId][$key] = MapNode::create([
                    'id'            => Str::uuid(),
                    'map_id'        => $mapId,
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
        }

        // 3. VERBINDUNGEN DEFINIEREN
        $edgesData = [
            'erp' => [
                ['source' => 'core', 'target' => 'etsy', 'label' => 'Sync API', 'desc' => 'Importiert Etsy JSON zu internen Orders', 'status' => 'active'],
                ['source' => 'stripe', 'target' => 'core', 'label' => 'Webhooks', 'desc' => 'Bestätigt Zahlungen in Echtzeit', 'status' => 'active'],
                ['source' => 'stripe', 'target' => 'finom', 'label' => 'Payouts', 'desc' => 'Tägliche Payouts von Stripe an Finom', 'status' => 'active'],
                ['source' => 'core', 'target' => 'datev', 'label' => 'UStVA ZIP', 'desc' => 'Generiert DATEV-CSV', 'status' => 'inactive'],
                ['source' => 'core', 'target' => 'eric', 'label' => 'XML Transfer', 'desc' => 'Native UStVA Direktübertragung ans Finanzamt', 'status' => 'active'],
                ['source' => 'mittwald', 'target' => 'core', 'label' => 'Hosting', 'desc' => 'Ubuntu Space für Laravel Applikation', 'status' => 'active'],
                ['source' => 'core', 'target' => 'firebase', 'label' => 'FCM Tokens', 'desc' => 'Sendet Pushes über die App', 'status' => 'active'],
                ['source' => 'core', 'target' => 'google', 'label' => 'OAuth', 'desc' => 'Social Login Registrierung', 'status' => 'active'],
                ['source' => 'core', 'target' => 'dhl', 'label' => 'Labels', 'desc' => 'Erzeugt PDF Versandmarken', 'status' => 'planned'],
            ],
            'ai' => [
                ['source' => 'user', 'target' => 'js', 'label' => 'Spricht', 'desc' => 'Audio-Signal via Computermikrofon', 'status' => 'active'],
                ['source' => 'js', 'target' => 'livewire', 'label' => 'Transkript (Text)', 'desc' => 'Browser übersetzt Audio in String', 'status' => 'active'],
                ['source' => 'livewire', 'target' => 'agent', 'label' => 'sendMessage()', 'desc' => 'Context & Historie wird verpackt', 'status' => 'active'],
                ['source' => 'agent', 'target' => 'llm', 'label' => 'Prompt Request', 'desc' => 'Remote API Call an LLM', 'status' => 'active'],
                ['source' => 'llm', 'target' => 'agent', 'label' => 'Tool Call', 'desc' => 'z.B. create_todo() angefragt', 'status' => 'active'],
                ['source' => 'agent', 'target' => 'registry', 'label' => 'Execute Tool', 'desc' => 'Verifikation & Parameter Übergabe', 'status' => 'active'],
                ['source' => 'registry', 'target' => 'db', 'label' => 'ORM Action', 'desc' => 'Datenbank read/write Action', 'status' => 'active'],
                ['source' => 'db', 'target' => 'registry', 'label' => 'Result[]', 'desc' => 'Eloquent Model Collection / Array', 'status' => 'active'],
                ['source' => 'registry', 'target' => 'agent', 'label' => 'Inject JSON Context', 'desc' => 'Versteckte Event Returns entfernen', 'status' => 'active'],
                ['source' => 'llm', 'target' => 'livewire', 'label' => 'Final Response', 'desc' => 'Antwort Text + Navigations Payload', 'status' => 'active'],
                ['source' => 'livewire', 'target' => 'user', 'label' => 'Audioausgabe (TTS)', 'desc' => 'Verifizierte Stimme liest Antwort vor', 'status' => 'active'],
            ]
        ];

        foreach ($edgesData as $mapId => $edgesGroup) {
            foreach ($edgesGroup as $edge) {
                MapEdge::create([
                    'id'          => Str::uuid(),
                    'map_id'      => $mapId,
                    'source_id'   => $allNodes[$mapId][$edge['source']]->id,
                    'target_id'   => $allNodes[$mapId][$edge['target']]->id,
                    'label'       => $edge['label'],
                    'description' => $edge['desc'],
                    'status'      => $edge['status'],
                ]);
            }
        }
    }
}
