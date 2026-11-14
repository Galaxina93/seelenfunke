<?php

namespace Tests\Feature\System;

use App\Console\Commands\SystemShopAssetManifestCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StageShopAssetManifestTest extends TestCase
{
    public function test_allowed_shop_asset_whitelist_accepts_only_pure_public_shop_paths(): void
    {
        // Erlaubte reine Shop-, 3D-, Vorlagen-, Konfigurator- & Blog-Pfade
        $validPaths = [
            'public/produkte/products/seelen-kristall/overlay.png',
            'public/produkte/products/seelen-kristall/t_seelenk.glb',
            'public/produkte/products/ki-agenten-playbook/playbook-cover.jpg',
            'public/produkte/product-templates/seelen-kristall-weihnachten.jpg',
            'public/produkte/laser-anleitungen/Anleitung_1_360_Grad_Gravur_xTool_RA3.pdf',
            'public/produkte/configurator/vectors/manifest.json',
            'public/produkte/configurator/quotes/manifest.json',
            'public/produkte/configurator/tutorial/configurator-tutorial-default.mp4',
            'produkte/products-secure/KI_Agenten_Management_Paket.zip',
            'public/marketing/blog/herzensprojekt.webp',
            'public/shopverwaltung/images/projekt/about/gruender-profil.webp',
        ];

        foreach ($validPaths as $valid) {
            $this->assertTrue(
                SystemShopAssetManifestCommand::isAllowedShopAssetRelativePath($valid),
                "Gültiger Shop-Pfad wurde fälschlich abgewiesen: {$valid}"
            );
        }
    }

    public function test_music_accounting_management_health_and_runtime_secrets_are_hard_blocked(): void
    {
        $forbiddenPaths = [
            'leitung/music/tracks/track_01.mp3',
            'leitung/music/tracks/track_01.m4a',
            'leitung/music/playlists_catalog.json',
            'buchhaltung/receipts/2026-11-01_Rechnung.pdf',
            'buchhaltung/contracts/Gewerbeversicherung.pdf',
            'buchhaltung/erictresor/zertifikat.pfx',
            'buchhaltung/invoices/RE-2026-1001.pdf',
            'agenten/workspace/Gesundheit/befund.pdf',
            'agenten/workspace/Dokumente/Kameraaufnahmen/clip.mp4',
            'public/support/wiki/health/rezept.jpg',
            'public/support/tickets/upload.png',
            'public/leitung/person_profiles/avatar.webp',
            'leitung/crm/mail-attachments/test.pdf',
            'leitung/tasks/anhang.pdf',
            'private/system-brain-map.json',
            'private/shop-asset-manifest.json',
            'system/Mein-Seelenfunke-db-backup/backup.zip',
            'firebase-service-account.json',
            '.env',
            '.env.production',
            'public/produkte/products/echtgold-seelen-herz/overlay.png',
            'public/produkte/products/seelen-kristall/backup/old.png',
            'public/produkte/products/seelen-kristall/overlay_orig_backup.png',
            'public/produkte/reviews/customer_photo.jpg',
            'produkte/products-secure/seelen-kristall/documents/1793959066_agb.pdf',
            'public/produkte/../../.env',
            'public/produkte/shell.php',
            'public/marketing/blog/leak.json',
        ];

        foreach ($forbiddenPaths as $forbidden) {
            $this->assertFalse(
                SystemShopAssetManifestCommand::isAllowedShopAssetRelativePath($forbidden),
                "Gesperrter Pfad wurde NICHT blockiert: {$forbidden}"
            );
        }
    }

    public function test_artisan_shop_asset_manifest_generates_valid_zero_leakage_json(): void
    {
        $exitCode = Artisan::call('system:shop-asset-manifest', ['--json' => true]);
        $this->assertSame(0, $exitCode);

        $output = trim(Artisan::output());
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame(2, $decoded['version'] ?? null);
        $this->assertSame('pure_shop_assets_only', $decoded['scope'] ?? null);
        $this->assertIsArray($decoded['files'] ?? null);

        foreach ($decoded['files'] as $entry) {
            $rel = (string) ($entry['rel_path'] ?? '');
            $this->assertTrue(
                SystemShopAssetManifestCommand::isAllowedShopAssetRelativePath($rel),
                "Manifest enthält unzulässigen Pfad: {$rel}"
            );
            $this->assertSame(64, strlen((string) ($entry['sha256'] ?? '')));
            $this->assertGreaterThan(0, (int) ($entry['size'] ?? 0));
            $this->assertStringNotContainsString('buchhaltung', strtolower($rel));
            $this->assertStringNotContainsString('music', strtolower($rel));
            $this->assertStringNotContainsString('wiki/health', strtolower($rel));
        }
    }

    public function test_funki_script_contains_hard_stage_only_air_gap_guards(): void
    {
        $funkiContent = (string) file_get_contents(base_path('funki'));
        $this->assertStringContainsString('stage-deploy)', $funkiContent);
        $this->assertStringContainsString('seelenfunke-live', $funkiContent);
        $this->assertStringContainsString('https://stage.mein-seelenfunke.de', $funkiContent);
    }
}
