<?php

use App\Services\Laser\XtoolLaserCockpitService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nicht-destruktive Synchronisation der benannten Multi-Laserprofile (xtool_profiles)
 * und echten Lamellenrost-Standardwerte auf allen physischen Laser-Produkten & der Alu-Gutscheinkarte.
 *
 * - Preis, Bestand, Galerie, 3D-Modelle, 2D-Overlays und Vorlagen bleiben zu 100 % unangetastet.
 * - Die 33,1 mm / 32,8 mm Master-Referenz des Seelen Kristalls bleibt erhalten.
 * - Idempotent: Ein erneuter Lauf erzeugt keinerlei unnötige DB-Schreibzugriffe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'configurator_settings')) {
            return;
        }

        $rows = DB::table('products')->get(['id', 'slug', 'type', 'configurator_settings']);

        foreach ($rows as $row) {
            $slug = trim((string) ($row->slug ?? ''));
            $type = trim((string) ($row->type ?? 'physical'));
            if ($type !== 'physical' && $slug !== 'geschenkgutschein') {
                continue;
            }

            $current = is_string($row->configurator_settings)
                ? (json_decode($row->configurator_settings, true) ?: [])
                : (is_array($row->configurator_settings) ? $row->configurator_settings : []);

            $updated = XtoolLaserCockpitService::applySeededMultiLaserProfilesToSettings($slug, $current);

            $encodedBefore = json_encode($current, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $encodedAfter = json_encode($updated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($encodedBefore === $encodedAfter) {
                continue;
            }

            DB::table('products')
                ->where('id', $row->id)
                ->update([
                    'configurator_settings' => $encodedAfter,
                    'updated_at'            => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Bewusst nicht-destruktiv: hinterlegte Laserprofile bleiben erhalten.
    }
};
