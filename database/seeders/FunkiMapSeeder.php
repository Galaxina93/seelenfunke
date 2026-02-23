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
            'id' => Str::uuid(), 'label' => 'Mein-Seelenfunke', 'description' => 'Das Herzstück / ERP', 'icon' => 'sparkles', 'type' => 'core', 'status' => 'active', 'link' => 'https://mein-seelenfunke.de', 'pos_x' => 50, 'pos_y' => 50
        ]);

        // VERTRIEB
        $shop = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Eigener Shop', 'description' => 'Hauptumsatz & Konfigurator', 'icon' => 'shopping-bag', 'type' => 'sales', 'status' => 'active', 'link' => 'https://mein-seelenfunke.de/shop', 'pos_x' => 30, 'pos_y' => 20]);
        $etsy = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Etsy', 'description' => 'Digitale Downloads & Traffic', 'icon' => 'etsy', 'type' => 'sales', 'status' => 'planned', 'link' => 'https://etsy.com', 'pos_x' => 70, 'pos_y' => 20]);

        // FINANZEN & STEUERN
        $stripe = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Stripe API', 'description' => 'Kreditkarten, Apple Pay, Klarna', 'icon' => 'stripe', 'type' => 'finance', 'status' => 'active', 'link' => 'https://dashboard.stripe.com', 'pos_x' => 20, 'pos_y' => 80]);
        $bank = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Bank Finom', 'description' => 'Geschäftskonto & Kreditkarten', 'icon' => 'finom', 'type' => 'finance', 'status' => 'active', 'link' => 'https://finom.co', 'pos_x' => 50, 'pos_y' => 85]);
        $datev = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'DATEV', 'description' => 'Steuer Export & Schnittstelle', 'icon' => 'datev', 'type' => 'api', 'status' => 'inactive', 'link' => 'https://datev.de', 'pos_x' => 80, 'pos_y' => 80]);

        // API & INFRASTRUKTUR (Korrekt positioniert)
        $mittwald = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Mittwald', 'description' => 'Server & Hosting', 'icon' => 'mittwald', 'type' => 'api', 'status' => 'active', 'link' => 'https://mittwald.de', 'pos_x' => 10, 'pos_y' => 50]);
        $firebase = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Firebase API', 'description' => 'Push Notifications', 'icon' => 'firebase', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.firebase.google.com', 'pos_x' => 90, 'pos_y' => 50]);
        $google = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'Google API', 'description' => 'Maps, Fonts, Analytics, Auth', 'icon' => 'google', 'type' => 'api', 'status' => 'active', 'link' => 'https://console.cloud.google.com', 'pos_x' => 85, 'pos_y' => 30]);
        $dhl = FunkiMapNode::create(['id' => Str::uuid(), 'label' => 'DHL API', 'description' => 'Automatischer Label-Druck', 'icon' => 'dhl', 'type' => 'api', 'status' => 'planned', 'link' => 'https://developer.dhl.com', 'pos_x' => 15, 'pos_y' => 30]);

        // VERBINDUNGEN (EDGES) mit Beschreibungen
        FunkiMapEdge::create(['id' => Str::uuid(), 'source_id' => $core->id, 'target_id' => $shop->id, 'label' => 'Bestellungen', 'description' => 'Verarbeitet Kundenbestellungen inkl. 3D-Konfiguration', 'status' => 'active']);
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
