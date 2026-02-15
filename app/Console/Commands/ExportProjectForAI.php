<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ExportProjectForAI extends Command
{
    protected $signature = 'project:export {--output=project_context.txt}';
    protected $description = 'Verschlankter Export: Fokus auf Logik, UI und Models.';

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
        $outputFile = $this->option('output');
        $startTime = microtime(true);

        $this->info("Funki startet die Schlankheitskur...");

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

        $content = "PROJECT LOGIC SUMMARY (SLIM)\n============================\n\n";

        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $fileContent = $file->getContents();

            // --- TOKEN-SAVING LOGIK ---

            // 1. PHP Kommentare entfernen (Doku-Blöcke fressen extrem viel Platz)
            if ($file->getExtension() === 'php') {
                // Entfernt /* ... */ Kommentare
                $fileContent = preg_replace('!/\*.*?\*/!s', '', $fileContent);
                // Entfernt // Kommentare (nur wenn sie allein in der Zeile stehen)
                $fileContent = preg_replace('/^\s*\/\/.*$/m', '', $fileContent);
            }

            // 2. Mehrfache Leerzeilen auf eine reduzieren
            $fileContent = preg_replace("/\n\s*\n+/", "\n", $fileContent);

            // 3. Unnötige Leerzeichen an Zeilenenden trimmen
            $fileContent = implode("\n", array_map('trim', explode("\n", $fileContent)));

            $content .= "--- FILE: {$relativePath} ---\n";
            $content .= trim($fileContent) . "\n\n";

            $this->line("Verschlankt: <comment>{$relativePath}</comment>");
        }

        File::put(base_path($outputFile), $content);

        $duration = round(microtime(true) - $startTime, 2);
        $size = round(File::size(base_path($outputFile)) / 1024, 2); // In KB statt MB

        $this->info("Export abgeschlossen!");
        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Dauer', $duration . ' Sek.'],
                ['Größe neu', $size . ' KB'],
                ['Speicherort', base_path($outputFile)],
            ]
        );

        return Command::SUCCESS;
    }
}
