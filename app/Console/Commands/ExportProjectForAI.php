<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

class ExportProjectForAI extends Command
{
    protected $signature = 'project:export
                            {--output=project_context.txt : Name der Zieldatei}
                            {--migrations : Exportiert nur die Datenbank-Migrationen}
                            {--short : Exportiert nur Kern-Logik-Ordner}
                            {--importants : Exportiert nur die absolut wichtigsten Projektdateien}
                            {--search= : Exportiert nur Dateien, die eines der Wörter (kommagetrennt) im Pfad oder Inhalt enthalten (z.B. ticket, email, user)}';

    protected $description = 'Radikaler Export: Extreme Minification auf Token-Ebene für maximale Token-Ersparnis.';

    protected $ignoredDirectories = [
        'vendor', 'node_modules', 'storage', 'public', '.git', '.idea', '.vscode',
        'bootstrap', 'tests', 'database/factories', 'database/seeders',
        'app/Providers', 'app/Console', 'config', 'lang', 'resources/lang'
    ];

    protected $allowedExtensions = ['php', 'js'];

    protected $ignoredFiles = [
        'composer.lock', 'package-lock.json', 'yarn.lock', 'phpunit.xml',
        'webpack.mix.js', 'vite.config.js', 'tailwind.config.js', 'postcss.config.js',
        'package.json', 'composer.json'
    ];

    public function handle()
    {
        $startTime = microtime(true);
        $outputFile = $this->option('output');

        // Such-String in ein Array aufteilen und leere Einträge entfernen
        $searchString = $this->option('search');
        $searchTerms = [];
        if (!empty($searchString)) {
            $searchTerms = array_filter(array_map('trim', explode(',', $searchString)));
        }

        if ($this->option('migrations')) {
            $outputFile = $outputFile === 'project_context.txt' ? 'migrations.txt' : $outputFile;
            $paths = [base_path('database/migrations')];
            $this->ignoredDirectories = [];
        } elseif ($this->option('importants')) {
            $paths = [
                base_path('app/Models'),
                base_path('app/Livewire'),
                base_path('app/Services'),
                base_path('routes'),
            ];
        } elseif ($this->option('short')) {
            $paths = [
                base_path('app/Http'),
                base_path('routes'),
                base_path('resources'),
                base_path('app/Livewire'),
                base_path('app/Console/Commands'),
            ];
        } else {
            $paths = [base_path()];
            $this->ignoredDirectories[] = 'database/migrations';
        }

        $validPaths = array_filter($paths, fn($path) => File::exists($path));

        if (empty($validPaths)) {
            $this->error("Keine gültigen Pfade zum Exportieren gefunden.");
            return Command::FAILURE;
        }

        $finder = new Finder();
        $finder->ignoreDotFiles(true)
            ->in($validPaths)
            ->exclude($this->ignoredDirectories);

        $finder->filter(function (\SplFileInfo $file) use ($searchTerms) {
            if ($file->isDir()) return false;
            if (in_array($file->getBasename(), $this->ignoredFiles)) return false;

            // NEU: Filtern nach MEHREREN Suchbegriffen (ODER-Logik)
            if (!empty($searchTerms)) {
                $matched = false;
                $filePath = $file->getRealPath();
                $fileContent = null; // Lazy Loading für den Datei-Inhalt

                foreach ($searchTerms as $term) {
                    // 1. Prüfe, ob der Begriff im Pfad/Dateinamen steht
                    if (stripos($filePath, $term) !== false) {
                        $matched = true;
                        break; // Treffer gefunden, keine weiteren Begriffe prüfen
                    }

                    // 2. Prüfe den Inhalt nur, wenn es nötig ist (spart extrem viel Arbeitsspeicher/Zeit)
                    if ($fileContent === null) {
                        $fileContent = file_get_contents($filePath);
                    }

                    if (stripos($fileContent, $term) !== false) {
                        $matched = true;
                        break; // Treffer gefunden, keine weiteren Begriffe prüfen
                    }
                }

                // Wenn keines der Wörter gefunden wurde, Datei ignorieren
                if (!$matched) {
                    return false;
                }
            }

            $isBlade = str_ends_with($file->getBasename(), '.blade.php');
            if ($isBlade) return true;

            return in_array($file->getExtension(), $this->allowedExtensions);
        });

        // Speicherschonendes Schreiben direkt in die Datei
        $handle = fopen(base_path($outputFile), 'w');
        if (!$handle) {
            $this->error("Konnte Zieldatei nicht öffnen.");
            return Command::FAILURE;
        }

        $exportType = $this->option('migrations') ? 'DATABASE MIGRATIONS' : 'PROJECT LOGIC';
        if (!empty($searchTerms)) {
            $exportType .= " (FILTERED BY: " . implode(', ', $searchTerms) . ")";
        }

        fwrite($handle, "{$exportType}\n====================\n");

        $fileCount = 0;

        foreach ($finder as $file) {
            $relativePath = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file->getRealPath());
            $isBlade = str_ends_with($file->getBasename(), '.blade.php');

            $fileContent = $this->cleanContent($file->getRealPath(), $file->getContents(), $file->getExtension(), $isBlade);

            if (!empty($fileContent)) {
                // Keine extra Leerzeilen, kompakteste Darstellung
                fwrite($handle, "---FILE:{$relativePath}---\n{$fileContent}\n");
                $this->line("Minifiziert: <comment>{$relativePath}</comment>");
                $fileCount++;
            }
        }

        fclose($handle);
        $this->finishExport($startTime, $outputFile, $fileCount);

        return Command::SUCCESS;
    }

    /**
     * Radikale Minifizierung auf Token-Basis.
     */
    protected function cleanContent($filePath, $content, $extension, $isBlade = false)
    {
        // 1. Extreme Komprimierung für reine PHP Dateien (Kein Blade)
        if ($extension === 'php' && !$isBlade) {
            $tokens = token_get_all(file_get_contents($filePath));
            $output = '';

            foreach ($tokens as $token) {
                if (is_string($token)) {
                    $output .= $token;
                } else {
                    $id = $token[0];
                    $text = $token[1];

                    if (in_array($id, [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE])) continue;

                    if ($output !== '') {
                        $lastChar = substr($output, -1);
                        $firstChar = substr($text, 0, 1);
                        if (preg_match('/[a-zA-Z0-9_]/', $lastChar) && preg_match('/[a-zA-Z0-9_\$]/', $firstChar)) {
                            $output .= ' ';
                        }
                    }
                    $output .= $text;
                }
            }
            return trim($output);
        }

        // 2. Blade & JS
        if ($isBlade) {
            $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
            $content = preg_replace('//s', '', $content);
        }

        if ($extension === 'js') {
            $content = preg_replace('!/\*.*?\*/!s', '', $content);
            $content = preg_replace('/^(?:(?!\/\/).)*\K\/\/.*$/m', '', $content);
        }

        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\s*([{}()\[\];])\s*/', '$1', $content);

        return trim($content);
    }

    protected function finishExport($startTime, $outputFile, $fileCount)
    {
        $duration = round(microtime(true) - $startTime, 2);
        $size = round(File::size(base_path($outputFile)) / 1024, 2);

        $this->info("Export erfolgreich!");
        $this->table(
            ['Metrik', 'Wert'],
            [
                ['Dateien', $fileCount],
                ['Dauer', $duration . ' Sek.'],
                ['Größe', $size . ' KB'],
            ]
        );
    }
}
