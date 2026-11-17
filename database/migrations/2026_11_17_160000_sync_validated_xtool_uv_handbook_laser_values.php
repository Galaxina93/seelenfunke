<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Non-destruktiver Live-DB-Sync (lokal & Stage) der validierten xTool F2 Ultra UV (5 W · 355 nm) Laserwerte
 * aus beiden Werkstatthandbüchern (PDF 1 Pos. 01-16, PDF 2 Laserwerte-Datenbank 11/2026).
 *
 * - Ausschließlich configurator_settings wird per array_merge ergänzt / aktualisiert.
 * - Preis, Bestand, Bilder, Geometrie (xtool_plane_*, area_*, custom_points, Kontur) bleiben unangetastet.
 * - Bestehende Werkstatt-Texte (Material, Hinweis, Equipment, Prozess, Gravurmodus) bleiben erhalten, wenn gesetzt.
 * - Idempotent: Ein erneuter Lauf erzeugt keinerlei Änderungen.
 */
return new class extends Migration
{
    /** Diese Schlüssel werden nur gesetzt, wenn das Produkt noch keinen Wert besitzt. */
    private const PRESERVE_IF_PRESENT = [
        'xtool_material',
        'xtool_setup_note',
        'xtool_equipment_key',
        'xtool_process_mode',
        'xtool_engrave_mode',
        'xtool_preset_mode',
        'xtool_element_type',
        'xtool_engrave_tab',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('products') || !Schema::hasColumn('products', 'configurator_settings')) {
            return;
        }

        foreach (self::validatedSettings() as $slug => $values) {
            $rows = DB::table('products')->where('slug', $slug)->get(['id', 'configurator_settings']);
            foreach ($rows as $row) {
                $current = is_string($row->configurator_settings)
                    ? (json_decode($row->configurator_settings, true) ?: [])
                    : (is_array($row->configurator_settings) ? $row->configurator_settings : []);

                $patch = [];
                foreach ($values as $key => $value) {
                    if (in_array($key, self::PRESERVE_IF_PRESENT, true)
                        && array_key_exists($key, $current)
                        && trim((string) $current[$key]) !== '') {
                        continue;
                    }
                    $patch[$key] = $value;
                }

                $merged = array_merge($current, $patch);
                if ($merged == $current) {
                    continue;
                }

                DB::table('products')->where('id', $row->id)->update([
                    'configurator_settings' => json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at'            => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Bewusst nicht destruktiv: validierte Handbuchwerte bleiben beim Rollback erhalten.
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function validatedSettings(): array
    {
        return [
            'seelen-kristall' => self::build(
                ['preset' => 'pdf2_seidenmatt_frost', 'polarity' => 'white_on_dark', 'equipment' => 'flatbed_standard', 'material' => 'K9 Kristalltrophäe', 'note' => 'Schwarzes Lamellenrost nutzen, Standfuß frei über Vorderkante schweben lassen. Shop-SVG nicht invertieren.'],
                [33.1, 60, 80, 40, 300, 1],
                [33.1, 100, 450, 40, 500, 1, 'Jarvis']
            ),
            'seelen-anhaenger' => self::build(
                ['preset' => 'pdf2_silber_kontrast', 'polarity' => 'white_on_dark', 'equipment' => 'flatbed_standard', 'material' => '0,2 mm Aluminiumlegierung', 'note' => 'Jig-Fokus 19,9 mm (Flachbett ohne Jig 1,8 mm). Foto invertiert mit Dot-Gain-Gamma.'],
                [19.9, 70, 450, 40, 300, 1],
                [19.9, 85, 280, 40, 600, 1, 'Jarvis']
            ),
            'weizenglas-personalisiert' => self::build(
                ['preset' => 'pdf2_ra3_frost', 'polarity' => 'white_on_dark', 'equipment' => 'rotary_ra3', 'material' => 'Klares Glas (Kristall-Weizenbierglas)', 'note' => 'RA3 MagSwap, Wasserwaage Pflicht, 30 mm zum Trinkrand, 40 mm zum Fuß.'],
                [74.0, 55, 380, 40, 300, 1],
                [74.0, 55, 260, 40, 400, 1, 'Jarvis']
            ),
            'seelen-talisman' => self::build(
                ['preset' => 'pdf2_sweetspot', 'polarity' => 'dark_on_light', 'equipment' => 'flatbed_standard', 'material' => 'Edelstahl Gold-Finish', 'note' => 'Schlüsselring frei über die Lamellenkante hängen lassen.'],
                [3.6, 44, 105, 65, 330, 1],
                [3.5, 46, 215, 65, 800, 1, 'Jarvis']
            ),
            'seelen-pfote-hundemarke' => self::build(
                ['preset' => 'pdf2_sweetspot', 'polarity' => 'dark_on_light', 'equipment' => 'flatbed_standard', 'material' => 'Edelstahl 14K IP vergoldet (PVD)', 'note' => 'Schwarzes Lamellenrost, vor und nach dem Lasern mit Isopropanol reinigen.'],
                [3.2, 42, 110, 65, 330, 1],
                [3.2, 45, 210, 65, 800, 1, 'Jarvis']
            ),
            'goldenes-seelen-herz' => self::build(
                ['preset' => 'pdf2_sweetspot', 'polarity' => 'dark_on_light', 'equipment' => 'flatbed_standard', 'material' => 'Edelstahl 14K IP vergoldet (massiv)', 'note' => 'Kette abnehmen, Herz flach auf das schwarze Lamellenrost legen.'],
                [5.4, 46, 100, 65, 350, 1],
                [5.4, 48, 220, 65, 800, 1, 'Jarvis']
            ),
            'id-armband-silber-herz' => self::build(
                ['preset' => 'pdf2_sweetspot', 'polarity' => 'dark_on_light', 'equipment' => 'flatbed_standard', 'material' => '925er Sterlingsilber', 'note' => 'Auf der schwarzen Schaumstoffmatte fixieren.'],
                [2.2, 75, 180, 50, 400, 2],
                [2.2, 75, 240, 50, 1000, 2, 'Jarvis']
            ),
            'id-armband-gold-herz' => self::build(
                ['preset' => 'pdf2_sweetspot', 'polarity' => 'dark_on_light', 'equipment' => 'flatbed_standard', 'material' => '925er Gold-Vermeil', 'note' => 'Auf der schwarzen Schaumstoffmatte fixieren (Schon-Oxidation).'],
                [2.5, 34, 130, 70, 400, 1],
                [2.5, 34, 180, 70, 1000, 1, 'Jarvis']
            ),
            'echtgold-seelen-herz' => self::build(
                ['preset' => 'pdf1_pos08', 'polarity' => 'dark_on_light', 'equipment' => 'flatbed_standard', 'material' => '585er Gold', 'note' => 'Für Dunkelton Offset +0,8 mm (1,6 mm).'],
                [0.8, 70, 80, 60, 800, 2],
                null
            ),
            'geschenkgutschein' => self::build(
                ['preset' => 'karte_schwarz', 'polarity' => 'white_on_dark', 'equipment' => 'flatbed_standard', 'material' => 'Eloxiertes Aluminium (Visitenkarte)', 'note' => 'Schwarze Karte 0,8 mm (Jig 19,9 mm). Gold-Karte +0,4 mm.'],
                [0.8, 45, 850, 55, 450, 1],
                [0.8, 45, 220, 55, 500, 1, 'Schwellenwert']
            ),
        ];
    }

    /**
     * @param array{preset: string, polarity: string, equipment: string, material: string, note: string} $meta
     * @param array{0: float, 1: int, 2: int, 3: int, 4: int, 5: int} $vector  Fokus, Leistung %, mm/s, kHz, L/cm, Pässe
     * @param array{0: float, 1: int, 2: int, 3: int, 4: int, 5: int, 6: string}|null $bitmap  Fokus, Leistung %, µs, kHz, DPI, Pässe, Modus
     * @return array<string, mixed>
     */
    private static function build(array $meta, array $vector, ?array $bitmap): array
    {
        [$vFocus, $vPower, $vSpeed, $vFreq, $vLpc, $vPasses] = $vector;
        $processModes = [
            'flatbed_standard' => 'Prozess auf flacher Oberfläche',
            'rotary_ra3'       => 'Walze - Rotationsaufsatz',
            'conveyor_batch'   => 'Gleiches Design - Stapelverarbeitung',
            'inner_3d'         => 'Innengravur - 3D-Miniatur',
        ];
        $note = "Gravieren · {$vPower} % · {$vSpeed} mm/s · {$vLpc} Linien/cm · {$vFreq} kHz" . ($vPasses > 1 ? " · {$vPasses}x" : '');

        $settings = [
            'xtool_handbook_preset'          => $meta['preset'],
            'xtool_photo_substrate_polarity' => $meta['polarity'],
            'xtool_equipment_key'            => $meta['equipment'],
            'xtool_process_mode'             => $processModes[$meta['equipment']] ?? 'Prozess auf flacher Oberfläche',
            'xtool_material'                 => $meta['material'],
            'xtool_setup_note'               => $meta['note'],
            'xtool_element_type'             => $bitmap !== null ? 'Bild' : 'Vektor',
            'xtool_engrave_tab'              => 'Gravieren',
            'xtool_preset_mode'              => 'Benutzerdefiniert',
            'xtool_engrave_mode'             => 'Bidirektional',
            'xtool_speed_mms'                => $vSpeed,
            'xtool_lpc'                      => $vLpc,
            'xtool_vector_note'              => $note,
            'xtool_vector_focus_mm'          => $vFocus,
            'xtool_vector_power_percent'     => $vPower,
            'xtool_vector_speed_mms'         => $vSpeed,
            'xtool_vector_frequency_khz'     => $vFreq,
            'xtool_vector_lpc'               => $vLpc,
            'xtool_vector_dpi'               => (int) round($vLpc * 2.54),
            'xtool_vector_passes'            => $vPasses,
        ];

        if ($bitmap !== null) {
            [$bFocus, $bPower, $bDot, $bFreq, $bDpi, $bPasses, $bMode] = $bitmap;
            $settings += [
                'xtool_focus_mm'               => $bFocus,
                'xtool_power_percent'          => $bPower,
                'xtool_dot_duration_us'        => $bDot,
                'xtool_frequency_khz'          => $bFreq,
                'xtool_dpi'                    => $bDpi,
                'xtool_passes'                 => $bPasses,
                'xtool_bitmap_mode'            => $bMode,
                'xtool_bitmap_focus_mm'        => $bFocus,
                'xtool_bitmap_power_percent'   => $bPower,
                'xtool_bitmap_dot_duration_us' => $bDot,
                'xtool_bitmap_frequency_khz'   => $bFreq,
                'xtool_bitmap_dpi'             => $bDpi,
                'xtool_bitmap_passes'          => $bPasses,
            ];
        } else {
            $settings += [
                'xtool_focus_mm'      => $vFocus,
                'xtool_power_percent' => $vPower,
                'xtool_frequency_khz' => $vFreq,
                'xtool_dpi'           => (int) round($vLpc * 2.54),
                'xtool_passes'        => $vPasses,
                'xtool_bitmap_mode'   => 'Schwellenwert',
            ];
        }

        return $settings;
    }
};
