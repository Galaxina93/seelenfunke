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

        // ZENTRUM
        $core = FunkiMapNode::create([
            'id' => Str::uuid(), 'label' => 'Mein-Seelenfunke', 'description' => 'Das Herzstück / ERP', 'icon' => 'sparkles', 'type' => 'core', 'status' => 'active', 'link' => 'https://mein-seelenfunke.de',                                          'pos_x' => 47.76, 'pos_y' => 51.7
        ]);

        // VERTRIEB
        $etsy = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Etsy', 'description' => 'Digitale Downloads & Traffic', 'icon' => 'etsy', 'type' => 'sales', 'status' => 'planned', 'link' => 'https://etsy.com',                                'pos_x' => 36.97, 'pos_y' => 7.03]);

        // FINANZEN & STEUERN
        $stripe = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Stripe API', 'description' => 'Kreditkarten, Apple Pay, Klarna', 'icon' => 'stripe', 'type' => 'finance', 'status' => 'active', 'link' => 'https://dashboard.stripe.com',      'pos_x' => 93.8, 'pos_y' => 55.05]);
        $bank = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Bank Finom', 'description' => 'Geschäftskonto & Kreditkarten', 'icon' => 'finom', 'type' => 'finance', 'status' => 'active', 'link' => 'https://finom.co',                       'pos_x' => 87.6, 'pos_y' => 75.14]);
        $datev = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'DATEV API', 'description' => 'Steuer Export & Schnittstelle', 'icon' => 'datev', 'type' => 'api', 'status' => 'inactive', 'link' => 'https://datev.de',                         'pos_x' => 61.36, 'pos_y' => 5.72]);

        // API & INFRASTRUKTUR (Korrekt positioniert)
        $mittwald = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Mittwald', 'description' => 'Server & Hosting', 'icon' => 'mittwald', 'type' => 'api', 'status' => 'active', 'link' => 'https://mittwald.de',                                'pos_x' => 21.28, 'pos_y' => 33.3]);
        $firebase = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Firebase API', 'description' => 'Push Notifications', 'icon' => 'firebase', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.firebase.google.com',          'pos_x' => 85.56, 'pos_y' => 26.61]);
        $google = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Google API', 'description' => 'Maps, Fonts, Analytics, Auth', 'icon' => 'google', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.cloud.google.com',         'pos_x' => 77.59, 'pos_y' => 19.01]);
        $dhl = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'DHL API', 'description' => 'Automatischer Label-Druck', 'icon' => 'dhl', 'type' => 'api', 'status' => 'planned', 'link' => 'https://developer.dhl.com',                           'pos_x' => 69.15, 'pos_y' => 12.17]);

        // VERBINDUNGEN (EDGES) mit Beschreibungen
        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $core->id, 'target_id' => $etsy->id, 'label' => 'Sync API', 'description' => 'Importiert Etsy JSON zu internen Orders', 'status' => 'planned']);

        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $stripe->id, 'target_id' => $core->id, 'label' => 'Webhooks', 'description' => 'Bestätigt Zahlungen in Echtzeit und ändert Status', 'status' => 'active']);
        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $stripe->id, 'target_id' => $bank->id, 'label' => 'Auszahlungen', 'description' => 'Tägliche Payouts von Stripe an Finom', 'status' => 'active']);

        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $core->id, 'target_id' => $datev->id, 'label' => 'UStVA Export', 'description' => 'Generiert DATEV-CSV und Belege als ZIP-Archiv', 'status' => 'inactive']);

        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $mittwald->id, 'target_id' => $core->id, 'label' => 'Hosting', 'description' => 'Ubuntu Space für Laravel Applikation', 'status' => 'active']);
        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $core->id, 'target_id' => $firebase->id, 'label' => 'FCM Tokens', 'description' => 'Sendet iOS/Android Pushes über die App', 'status' => 'active']);
        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $core->id, 'target_id' => $google->id, 'label' => 'OAuth', 'description' => 'Social Login für schnelle Kundenregistrierung', 'status' => 'active']);
        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $core->id, 'target_id' => $dhl->id, 'label' => 'Labels', 'description' => 'Erzeugt druckfertige PDF Versandmarken', 'status' => 'planned']);
    }
}
