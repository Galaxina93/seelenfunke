<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateAiMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:generate-map';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates an ai_map.md file containing an overview of Models, Controllers, Livewire Components, and Routes for the AI.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating AI Map...');

        $markdown = "# Seelenfunke System-Architektur & AI Map\n\n";
        $markdown .= "> Automatisch generiert am " . now()->format('Y-m-d H:i:s') . "\n\n";
        $markdown .= "Diese Datei dient als Index für die Seelenfunke-Codebase, um KI-Agenten die Orientierung zu erleichtern.\n\n";

        // 1. Models
        $markdown .= "## 1. Datenbank-Modelle (app/Models)\n\n";
        $markdown .= $this->scanDirectory(app_path('Models'), 'App\\Models');

        // 2. Livewire Components
        $markdown .= "## 2. Livewire Komponenten (app/Livewire)\n\n";
        $markdown .= $this->scanDirectory(app_path('Livewire'), 'App\\Livewire');

        // 3. Controllers
        $markdown .= "## 3. HTTP Controller (app/Http/Controllers)\n\n";
        $markdown .= $this->scanDirectory(app_path('Http/Controllers'), 'App\\Http\\Controllers');

        // 4. Services
        $markdown .= "## 4. Services & Geschäftslogik (app/Services)\n\n";
        $markdown .= $this->scanDirectory(app_path('Services'), 'App\\Services');

        // Save file
        $outputPath = base_path('ai_map.md');
        File::put($outputPath, $markdown);

        $this->info("AI Map successfully written to: {$outputPath}");
    }

    /**
     * Scans a directory and returns a markdown bulleted list of classes.
     */
    protected function scanDirectory($path, $namespacePrefix)
    {
        if (!File::exists($path)) {
            return "- *Verzeichnis existiert nicht*\n\n";
        }

        $files = File::allFiles($path);
        $output = "";

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = $file->getRelativePathname();
            $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $fullClass = $namespacePrefix . '\\' . $className;
            
            // Extract the class path for display
            $displayPath = Str::after($file->getPathname(), base_path() . '/');

            $output .= "- **`{$className}`** (`{$displayPath}`)\n";
        }

        return rtrim($output) . "\n\n";
    }
}
