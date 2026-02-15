<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ExportProjectForAI extends Command
{
    // Signatur erweitert um die --migrations Option
    protected $signature = 'project:export {--output=project_context.txt} {--migrations : Exportiert nur die Datenbank-Migrationen}';
    protected $description = 'Verschlankter Export: Fokus auf Logik, UI und Models (oder nur Migrationen).';

    /**
     * Radikale Reduzierung: Wir ignorieren alles, was Standard-Laravel ist
     * oder nur Metadaten enthält.
     */
    protected $ignoredDirectories = [
        'vendor', 'node_modules', 'storage', 'public', '.git', '.idea', '.vscode',
        'bootstrap', 'tests', 'database/migrations', 'database/factories',
        'app/Providers', 'app/Console', 'config' // Config & Provider sind meist Standard
    ];

    protected $allowedExtensions = ['php', 'blade.php', 'js'];

    protected $ignoredFiles = [
        'composer.lock', 'package-lock.json', 'yarn.lock', 'phpunit.xml',
        'webpack.mix.js', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js'
    ];

    public function handle()
    {
        // 1. CHECK: Sollen nur Migrationen exportiert werden?
        if ($this->option('migrations')) {
            return $this->exportMigrations();
        }

        // 2. STANDARD LOGIK (Dein ursprünglicher Code)
        $outputFile = $this->option('output');
        $startTime = microtime(true);

        $this->info("Funki startet die Schlankheitskur (Standard Export)...");

        $finder = new Finder();
        $finder->files()
            ->in(base_path())
            ->ignoreDotFiles(true)
            ->exclude($this->ignoredDirectories); // Hier werden migrations normal ignoriert

        // Filter für Standard-Export
        $finder->filter(function (\SplFileInfo $file) {
            if (in_array($file->getBasename(), $this->ignoredFiles)) return false;

            $extension = $file->getExtension();
            if (str_contains($file->getBasename(), '.blade.php')) return true;
            return in_array($extension, $this->allowedExtensions);
        });

        $content = "PROJECT LOGIC SUMMARY (SLIM)\n============================\n\n";

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $fileContent = $file->getContents();

            // Bereinigen (Kommentare etc.)
            $fileContent = $this->cleanContent($fileContent, $file->getExtension());

            $content .= "--- FILE: {$relativePath} ---\n";
            $content .= trim($fileContent) . "\n\n";

            $this->line("Verschlankt: <comment>{$relativePath}</comment>");
        }

        File::put(base_path($outputFile), $content);

        $this->finishExport($startTime, $outputFile);

        return Command::SUCCESS;
    }

    /**
     * Neue Logik: Nur Migrationen exportieren
     */
    protected function exportMigrations()
    {
        // Wenn kein individueller Output angegeben wurde, ändern wir den Standard-Namen
        $outputFile = $this->option('output') === 'project_context.txt'
            ? 'migrations.txt'
            : $this->option('output');

        $startTime = microtime(true);
        $this->info("Exportiere nur Datenbank-Migrationen...");

        $finder = new Finder();
        $finder->files()
            ->in(base_path('database/migrations')) // Nur dieser Ordner
            ->name('*.php') // Nur PHP Dateien
            ->sortByName(); // Wichtig: Nach Timestamp sortieren

        $content = "DATABASE MIGRATIONS ONLY\n========================\n\n";

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $fileContent = $file->getContents();

            // Auch hier unnötige Kommentare entfernen, um Tokens zu sparen
            $fileContent = $this->cleanContent($fileContent, 'php');

            $content .= "--- MIGRATION: {$relativePath} ---\n";
            $content .= trim($fileContent) . "\n\n";

            $this->line("Hinzugefügt: <comment>{$relativePath}</comment>");
        }

        File::put(base_path($outputFile), $content);

        $this->finishExport($startTime, $outputFile);

        return Command::SUCCESS;
    }

    /**
     * Hilfsfunktion: Code bereinigen (aus deinem Original-Code extrahiert)
     */
    protected function cleanContent($content, $extension)
    {
        // 1. PHP Kommentare entfernen
        if ($extension === 'php') {
            $content = preg_replace('!/\*.*?\*/!s', '', $content);
            $content = preg_replace('/^\s*\/\/.*$/m', '', $content);
        }

        // 2. Mehrfache Leerzeilen reduzieren
        $content = preg_replace("/\n\s*\n+/", "\n", $content);

        // 3. Trimmen
        return implode("\n", array_map('trim', explode("\n", $content)));
    }

    /**
     * Hilfsfunktion: Abschluss-Statistik
     */
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
