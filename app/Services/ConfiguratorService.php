<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ConfiguratorService
{
    /**
     * Gibt die Standard-Schriftarten für den Konfigurator zurück.
     * So hast du nur eine zentrale Stelle, wenn neue Fonts hinzukommen.
     */
    public function getFonts(): array
    {
        return [
            "Arial"            => "Arial, sans-serif",
            "Times New Roman"  => "Times New Roman, serif",
            "Verdana"          => "Verdana, sans-serif",
            "Courier New"      => "Courier New, monospace",
            "Georgia"          => "Georgia, serif",
            "ABeeZee"          => "'ABeeZee', sans-serif",
            "Alex Brush"       => "'Alex Brush', cursive",
            "Bebas Neue"       => "'Bebas Neue', sans-serif",
            "Caveat"           => "'Caveat', cursive",
            "Cinzel"           => "'Cinzel', serif",
            "Comfortaa"        => "'Comfortaa', cursive",
            "Dancing Script"   => "'Dancing Script', cursive",
            "Great Vibes"      => "'Great Vibes', cursive",
            "Lobster"          => "'Lobster', cursive",
            "Lobster Two"      => "'Lobster Two', cursive",
            "Marck Script"     => "'Marck Script', cursive",
            "Montserrat"       => "'Montserrat', sans-serif",
            "Pacifico"         => "'Pacifico', cursive",
            "Playfair Display" => "'Playfair Display', serif",
            "Righteous"        => "'Righteous', cursive",
            "Sacramento"       => "'Sacramento', cursive",
            "Satisfy"          => "'Satisfy', cursive"
        ];
    }

    /**
     * Liest automatisch alle SVGs aus dem public-Ordner aus und gruppiert sie nach Unterordnern.
     */
    public function getStandardVectors(): array
    {
        $vectorPath = public_path('shop/product/configurator/vectors');
        $vectors = [];

        if (File::exists($vectorPath)) {
            // Finde alle Dateien rekursiv
            $allFiles = File::allFiles($vectorPath);
            
            foreach ($allFiles as $file) {
                if (strtolower($file->getExtension()) === 'svg') {
                    $filename = $file->getRelativePathname(); // e.g. "Floral/flower.svg"
                    $rawName = str_replace('.svg', '', $file->getFilename());
                    $name = $this->formatVectorTitle($rawName);

                    // Kategorie aus dem relativen Pfad ermitteln
                    $relativePath = $file->getRelativePath();
                    $category = $relativePath ? ucwords(str_replace(['-', '_'], ' ', $relativePath)) : 'Allgemein';

                    if (!isset($vectors[$category])) {
                        $vectors[$category] = [];
                    }

                    $vectors[$category][] = [
                        'file' => str_replace('\\', '/', $filename), // Windows fallback
                        'name' => $name
                    ];
                }
            }
            
            // Sortierung: Liebe, Familie, Tiere, Natur, Symbole, Ornamente, Feiertage, Allgemein, Rest alphabetisch
            $order = ['Liebe', 'Familie', 'Tiere', 'Natur', 'Symbole', 'Ornamente', 'Feiertage', 'Allgemein'];
            uksort($vectors, function ($a, $b) use ($order) {
                $posA = array_search($a, $order, true);
                $posB = array_search($b, $order, true);
                if ($posA !== false && $posB !== false) return $posA <=> $posB;
                if ($posA !== false) return -1;
                if ($posB !== false) return 1;
                return strcasecmp($a, $b);
            });
        }

        return $vectors;
    }

    /**
     * Zentrale Definition aller Standard-Werte für das 3D/2D Setup.
     */
    public function getDefaultSettings(): array
    {
        return [
            // 2D Area
            'area_shape'   => 'rect',
            'area_left'    => 10,
            'area_top'     => 10,
            'area_width'   => 80,
            'area_height'  => 80,
            'custom_points'=> [
                ['x' => 20, 'y' => 20],
                ['x' => 80, 'y' => 20],
                ['x' => 80, 'y' => 80],
                ['x' => 20, 'y' => 80]
            ],

            // 3D Model
            'overlay_type' => 'plane',
            'material_type'=> 'glass',
            'model_scale'  => 100,
            'model_pos_x'  => 0,
            'model_pos_y'  => 0,
            'model_pos_z'  => 0,
            'model_rot_x'  => 0,
            'model_rot_y'  => 0,
            'model_rot_z'  => 0,

            // 3D Engraving / Overlay
            'engraving_scale' => 100,
            'engraving_pos_x' => 0,
            'engraving_pos_y' => 0,
            'engraving_pos_z' => 0,
            'engraving_rot_x' => 0,
            'engraving_rot_y' => 0,
            'engraving_rot_z' => 0,

            // Funktionalität
            'allow_logo'      => true,
            'allow_text_pos'  => true,
        ];
    }

    /**
     * Stellt sicher, dass keine JS-Fehler im Frontend/Backend entstehen,
     * weil ein Wert im Array fehlt.
     */
    public function mergeWithDefaults(?array $savedConfig): array
    {
        if (!$savedConfig) {
            $savedConfig = [];
        }

        return array_merge($this->getDefaultSettings(), $savedConfig);
    }

    /**
     * Erzeugt aus dem Roh-Dateinamen einen kundenfreundlichen deutschen Titel für Tooltips.
     */
    public function formatVectorTitle(string $rawName): string
    {
        $clean = str_replace(['-', '_'], ' ', $rawName);
        $name = ucwords($clean);

        $replacements = [
            'Fuesse' => 'Füße',
            'Fuss' => 'Fuß',
            'Laecheln' => 'Lächeln',
            'Maedchen' => 'Mädchen',
            'Glueck' => 'Glück',
            'Glueckskind' => 'Glückskind',
            'Gluecksklee' => 'Glücksklee',
            'Glueckshufeisen' => 'Glückshufeisen',
            'Bluete' => 'Blüte',
            'Lotusbluete' => 'Lotusblüte',
            'Schluessel' => 'Schlüssel',
            'Puzzlestueck' => 'Puzzlestück',
            'Muetze' => 'Mütze',
            'Fuer Immer' => 'Für immer',
            'Anstoessen' => 'Anstoßen',
            'Und' => '&',
            'Haeschen' => 'Häschen',
            'Amor Pfeil' => 'Amor-Pfeil',
            'Baby Junge' => 'Baby',
            'Baby Fuesse' => 'Baby-Füßchen',
            'Baby Laecheln' => 'Baby-Lächeln',
            'Baby Maedchen' => 'Baby-Mädchen',
            'Weihnachtsmann Muetze' => 'Weihnachtsmann-Mütze',
            'Schutz Und Geborgenheit' => 'Schutz & Geborgenheit',
            'Sekt Anstoessen' => 'Sekt zum Anstoßen',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $name);
    }
}

