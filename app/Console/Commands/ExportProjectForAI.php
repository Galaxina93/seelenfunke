<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ExportProjectForAI extends Command
{
    protected $signature = 'project:export {--output=project_context.txt} {--migrations : Exportiert nur die Datenbank-Migrationen} {--short : Exportiert nur Kern-Logik-Ordner (app/Http, routes, resources, Livewire, Commands)}';
    protected $description = 'Radikal verschlankter Export: Fokus auf Logik, Extreme Minification ohne Leerzeichen.';

    /**
     * Erweitert: Wir ignorieren irrelevante Ordner. 'public' bleibt hier ignoriert.
     */
    protected $ignoredDirectories = [
        'vendor', 'node_modules', 'storage', 'public', '.git', '.idea', '.vscode',
        'bootstrap', 'tests', 'database/migrations', 'database/factories', 'database/seeders',
        'app/Providers', 'app/Console', 'config', 'lang', 'resources/lang'
    ];

    protected $allowedExtensions = ['php', 'blade.php', 'js'];

    protected $ignoredFiles = [
        'composer.lock', 'package-lock.json', 'yarn.lock', 'phpunit.xml',
        'webpack.mix.js', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
        'package.json', 'composer.json'
    ];

    public function handle()
    {
        // 1. CHECK: Sollen nur Migrationen exportiert werden?
        if ($this->option('migrations')) {
            return $this->exportMigrations();
        }

        // 2. STANDARD ODER SHORT LOGIK
        $outputFile = $this->option('output');
        $startTime = microtime(true);
        $isShort = $this->option('short');

        $this->info($isShort ? "Funki startet den SHORT Export (Extreme Minification)..." : "Funki startet den Ultra-Slim Export (Extreme Minification)...");

        $finder = new Finder();
        $finder->ignoreDotFiles(true);

        if ($isShort) {
            // Nur die definierten Ordner (ohne 'public')
            $shortPaths = [
                base_path('app/Http'),
                base_path('routes'),
                base_path('resources'),
                base_path('app/Livewire'),
                base_path('app/Console/Commands'),
            ];

            // Nur existierende Pfade hinzufügen
            foreach ($shortPaths as $path) {
                if (File::exists($path)) {
                    $finder->in($path);
                }
            }
        } else {
            // Standard: Das gesamte Projektverzeichnis minus die ignoredDirectories
            $finder->in(base_path())
                ->exclude($this->ignoredDirectories);
        }

        // Dateien filtern
        $finder->filter(function (\SplFileInfo $file) {
            if (in_array($file->getBasename(), $this->ignoredFiles)) return false;

            $extension = $file->getExtension();
            if (str_contains($file->getBasename(), '.blade.php')) return true;
            return in_array($extension, $this->allowedExtensions);
        });

        $content = $isShort ? "PROJECT LOGIC (SHORT)\n====================\n" : "PROJECT LOGIC SUMMARY\n=====================\n";

        foreach ($finder as $file) {
            $relativePath = $isShort ? str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath()) : $file->getRelativePathname();
            $fileContent = $file->getContents();
            $isBlade = str_contains($file->getBasename(), '.blade.php');

            // Radikale Bereinigung / Minifizierung
            $fileContent = $this->cleanContent($fileContent, $file->getExtension(), $isBlade);

            // Nur hinzufügen, wenn die Datei nicht komplett leer ist
            if (!empty($fileContent)) {
                // Einzeiler pro Datei spart enorm Platz
                $content .= "--- FILE: {$relativePath} ---\n{$fileContent}\n\n";
                $this->line("Minifiziert: <comment>{$relativePath}</comment>");
            }
        }

        File::put(base_path($outputFile), $content);

        $this->finishExport($startTime, $outputFile);

        return Command::SUCCESS;
    }

    protected function exportMigrations()
    {
        $outputFile = $this->option('output') === 'project_context.txt'
            ? 'migrations.txt'
            : $this->option('output');

        $startTime = microtime(true);
        $this->info("Exportiere nur Datenbank-Migrationen...");

        $finder = new Finder();
        $finder->files()
            ->in(base_path('database/migrations'))
            ->name('*.php')
            ->sortByName();

        $content = "DATABASE MIGRATIONS ONLY\n========================\n";

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $fileContent = $file->getContents();

            $fileContent = $this->cleanContent($fileContent, 'php', false);

            if (!empty($fileContent)) {
                $content .= "--- MIGR: {$relativePath} ---\n{$fileContent}\n\n";
                $this->line("Minifiziert: <comment>{$relativePath}</comment>");
            }
        }

        File::put(base_path($outputFile), $content);

        $this->finishExport($startTime, $outputFile);

        return Command::SUCCESS;
    }

    /**
     * Extreme Minifizierung: Entfernt Kommentare, alle Zeilenumbrüche und quetscht den Code zusammen.
     */
    protected function cleanContent($content, $extension, $isBlade = false)
    {
        // 1. Blade & HTML Kommentare entfernen
        if ($isBlade) {
            $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
            $content = preg_replace('//s', '', $content);
        }

        // 2. PHP/JS Kommentare entfernen
        if ($extension === 'php' || $extension === 'js' || $isBlade) {
            $content = preg_replace('!/\*.*?\*/!s', '', $content);
            $content = preg_replace('/(?<!:)\/\/.*$/m', '', $content);
        }

        // 3. Alle Zeilenumbrüche, Tabs und mehrfachen Leerzeichen zu einem einzigen Leerzeichen machen
        $content = preg_replace('/\s+/', ' ', $content);

        // 4. Leerzeichen um syntaktische Zeichen entfernen ({, }, (, ), =, ;, ,, <, >, etc.)
        // Das quetscht den Code extrem zusammen, ist für eine KI aber problemlos lesbar.
        $content = preg_replace('/\s*([{}()\[\];,=><!?:]+)\s*/', '$1', $content);

        // 5. Sicherstellen, dass das PHP-Start-Tag ein Leerzeichen danach hat (wichtig für den Parser)
        $content = preg_replace('/<\?php/', '<?php ', $content);

        return trim($content);
    }

    protected function finishExport($startTime, $outputFile)
    {
        $duration = round(microtime(true) - $startTime, 2);
        $size = round(File::size(base_path($outputFile)) / 1024, 2);

        $this->info("Export abgeschlossen!");
        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Dauer', $duration . ' Sek.'],
                ['Größe neu', $size . ' KB'],
                ['Speicherort', base_path($outputFile)],
            ]
        );
    }
}
