<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product\ProductTemplate;
use Illuminate\Support\Facades\File;

class ExportProductTemplates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:export-templates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exportiert alle konfigurierten Produkt-Vorlagen als Seeder JSON-Datei.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $templates = ProductTemplate::with('product')->get();

        if ($templates->isEmpty()) {
            $this->warn('Keine Vorlagen in der Datenbank gefunden.');
            return;
        }

        $exportData = [];

        foreach ($templates as $template) {
            // Wenn das Produkt aus irgendeinem Grund gelöscht wurde, überspringen
            if (!$template->product) {
                continue;
            }

            $exportData[] = [
                'product_slug' => $template->product->slug, // Slug zur Identifizierung über DB-Resets hinweg
                'name' => $template->name,
                'configuration' => $template->configuration,
                'is_active' => $template->is_active,
                'holiday' => $template->holiday,
                'preview_image' => $template->preview_image,
            ];
        }

        $directory = database_path('seeders/data');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory . '/product_templates.json';

        File::put($path, json_encode($exportData, JSON_PRETTY_PRINT));

        $this->info(count($exportData) . ' Vorlagen erfolgreich exportiert nach: ' . $path);
    }
}
