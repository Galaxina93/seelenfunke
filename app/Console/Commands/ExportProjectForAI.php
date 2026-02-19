<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ExportProjectForAI extends Command
{
    protected $signature = 'project:export {--output=project_context.txt} {--migrations : Exportiert nur die Datenbank-Migrationen}';
    protected $description = 'Radikal verschlankter Export: Fokus auf Logik, ohne Leerzeilen und Kommentare.';

    /**
     * Erweitert: Wir ignorieren noch mehr irrelevante Ordner (Sprachen, Seeder, etc.)
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

        // 2. STANDARD LOGIK
        $outputFile = $this->option('output');
        $startTime = microtime(true);

        $this->info("Funki startet den Ultra-Slim Export (ohne Leerzeilen)...");

        $finder = new Finder();
        $finder->files()
            ->in(base_path())
            ->ignoreDotFiles(true)
            ->exclude($this->ignoredDirectories);

        $finder->filter(function (\SplFileInfo $file) {
            if (in_array($file->getBasename(), $this->ignoredFiles)) return false;

            $extension = $file->getExtension();
            if (str_contains($file->getBasename(), '.blade.php')) return true;
            return in_array($extension, $this->allowedExtensions);
        });

        $content = "PROJECT LOGIC SUMMARY (ULTRA SLIM)\n==================================\n";

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $fileContent = $file->getContents();
            $isBlade = str_contains($file->getBasename(), '.blade.php');

            // Radikale Bereinigung
            $fileContent = $this->cleanContent($fileContent, $file->getExtension(), $isBlade);

            // Nur hinzufügen, wenn die Datei nicht komplett leer ist
            if (!empty($fileContent)) {
                $content .= "--- FILE: {$relativePath} ---\n{$fileContent}\n\n";
                $this->line("Verschlankt: <comment>{$relativePath}</comment>");
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
                $content .= "--- MIGRATION: {$relativePath} ---\n{$fileContent}\n\n";
                $this->line("Hinzugefügt: <comment>{$relativePath}</comment>");
            }
        }

        File::put(base_path($outputFile), $content);

        $this->finishExport($startTime, $outputFile);

        return Command::SUCCESS;
    }

    /**
     * Radikale Bereinigung: Entfernt Kommentare, Leerzeilen und Einrückungen.
     */
    protected function cleanContent($content, $extension, $isBlade = false)
    {
        // 1. Blade & HTML Kommentare entfernen
        if ($isBlade) {
            $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
            $content = preg_replace('//s', '', $content);
        }

        // 2. PHP Kommentare entfernen
        if ($extension === 'php' || $isBlade) {
            // /* ... */
            $content = preg_replace('!/\*.*?\*/!s', '', $content);
            // // ... (aber http:// ignorieren)
            $content = preg_replace('/(?<!:)\/\/.*$/m', '', $content);
        }

        // 3. Zeilenweise trimmen und leere Zeilen vernichten
        $lines = explode("\n", $content);
        $cleanedLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Wenn die Zeile nach dem Trimmen nicht leer ist, übernehmen wir sie
            if ($trimmed !== '') {
                $cleanedLines[] = $trimmed;
            }
        }

        // Zusammenfügen mit einfachem Zeilenumbruch (ohne Einrückungen)
        return implode("\n", $cleanedLines);
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
