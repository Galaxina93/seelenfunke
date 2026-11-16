<?php

namespace App\Console\Commands;

use App\Services\System\BlueprintZeroLeakScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BlueprintScanCommand extends Command
{
    protected $signature = 'blueprint:scan
        {path? : Optionaler Pfad zu einer .zip-Datei oder einem Blueprint-Verzeichnis}
        {--build : Erzeugt vor dem Sicherheitsscan alle 5 Clean-Room-ZIP-Pakete frisch}';

    protected $description = 'Prüft digitale Architektur-Blueprints (.zip oder Ordner) gegen die harte Zero-Leak-Sperrliste.';

    public function handle(BlueprintZeroLeakScanner $scanner): int
    {
        if ($this->option('build')) {
            app(\App\Services\System\CleanRoomBlueprintBuilderService::class)->buildAllPackages();
        }

        $inputPath = $this->argument('path');
        $targetPath = is_string($inputPath) && trim($inputPath) !== ''
            ? trim($inputPath)
            : Storage::disk('local')->path('private/products-secure/blueprints');

        if (!file_exists($targetPath) && Storage::disk('local')->exists($targetPath)) {
            $targetPath = Storage::disk('local')->path($targetPath);
        }

        if (is_file($targetPath) && str_ends_with(strtolower($targetPath), '.zip')) {
            $report = $scanner->scanZipFile($targetPath);
        } elseif (is_dir($targetPath)) {
            $report = $scanner->scanDirectory($targetPath);
        } else {
            $this->error("Pfad nicht gefunden oder kein gültiges ZIP/Verzeichnis: {$targetPath}");
            return self::FAILURE;
        }

        if (!$report['clean'] || $report['hits_count'] > 0) {
            $this->error("ZERO-LEAK ALARM: {$report['hits_count']} Sperrlisten-Treffer gefunden!");
            $rows = array_map(
                fn (array $v) => [$v['entry'], $v['term'], $v['location']],
                $report['violations']
            );
            $this->table(['Datei / Eintrag', 'Sperrbegriff', 'Fundort'], $rows);
            return self::FAILURE;
        }

        $this->info("ZERO-LEAK OK: {$report['scanned_files']} Dateien geprüft, 0 Treffer.");

        return self::SUCCESS;
    }
}
