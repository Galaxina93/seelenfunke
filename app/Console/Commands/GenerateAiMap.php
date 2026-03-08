<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateAiMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:ai-map';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generiert die GenerateAiMap.md für den KI-Agenten (Antigravity)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generiere AI Context Map...');

        $mapContent = "# Mein Seelenfunke - AI Context Map\n\n";
        $mapContent .= "Diese Datei dient als Orientierung für den KI-Agenten. Sie enthält die aktuelle Dateistruktur und unumstößliche Projektregeln.\n\n";

        $mapContent .= "## 1. Projekt-DNA\n";
        $mapContent .= "- Laravel 11, Livewire 3, Tailwind CSS\n";
        $mapContent .= "- Kein klassischer Controller-Ansatz für das Frontend, stattdessen reine Livewire-Komponenten.\n\n";

        $mapContent .= "## 2. Verzeichnis-Struktur\n\n";

        // Hier definieren wir, welche Ordner für die KI relevant sind
        $directoriesToScan = [
            'app/Livewire' => 'Livewire Komponenten',
            'resources/views/livewire' => 'Livewire Blade Views',
            'app/Models' => 'Datenbank Models',
            'app/Mail' => 'Mails',
            'resources/views/global/mails' => 'Globale Mail Views'
        ];

        foreach ($directoriesToScan as $path => $label) {
            $mapContent .= "### {$label} (`{$path}/`)\n";
            $mapContent .= $this->scanDirectory(base_path($path));
            $mapContent .= "\n";
        }

        $mapContent .= "## 3. Globale Projekt-Regeln (Zwingend beachten!)\n\n";

        // --- Deine gesammelten Architektur-Gesetze ---
        $mapContent .= "- **Sprache:** Antworte und kommentiere Code immer auf Deutsch.\n";
        $mapContent .= "- **Ticketsystem:** Der Schlüssel für Tickets lautet im gesamten Code zwingend `funki_ticket_id` (nicht `ticket_id`). Die zugehörige View liegt unter `backend.admin.livewire.funki-ticket-system-component`.\n";
        $mapContent .= "- **Mail-Klassen:** Updates an Kunden werden über die Klasse `TicketUpdateMailToCustomer` gesendet (liegt direkt unter `app/Mail`). Für Newsletter-Tests ist zwingend die View `global.mails.newsletter.new_newsletter_test_mail_to_admin` zu nutzen.\n";
        $mapContent .= "- **Auth-Flow:** Die Registrierungs-View liegt unter `livewire.auth.register`. Der Namespace für die Livewire-Komponente ist `App\Livewire\Global\Auth`. Nach erfolgreicher Registrierung muss der User zwingend zur Login-Seite weitergeleitet werden.\n";
        $mapContent .= "- **Gamification:** Der korrekte Namespace für neue Komponenten in diesem Bereich ist `App\Livewire\Global\Gamification`.\n";
        $mapContent .= "- **Shop-Parameter:** In der Produkt-Template View (`livewire.shop.product.product-templates`) werden die Variablen strikt als `['templates' => \$templates, 'products' => \$products]` übergeben.\n";

        // Speichern im Hauptverzeichnis
        File::put(base_path('GenerateAiMap.md'), $mapContent);

        $this->info('Erfolg! Die Datei GenerateAiMap.md wurde im Hauptverzeichnis aktualisiert.');
    }

    /**
     * Rekursive Funktion zum Auslesen der Verzeichnisse.
     */
    private function scanDirectory($dir, $level = 0)
    {
        if (!File::isDirectory($dir)) {
            return "- *(Verzeichnis noch nicht erstellt)*\n";
        }

        $output = "";
        $files = File::files($dir);
        $directories = File::directories($dir);

        $indent = str_repeat("  ", $level);

        foreach ($directories as $directory) {
            $folderName = basename($directory);
            $output .= "{$indent}- **{$folderName}/**\n";
            $output .= $this->scanDirectory($directory, $level + 1);
        }

        foreach ($files as $file) {
            $fileName = $file->getFilename();
            $output .= "{$indent}- {$fileName}\n";
        }

        return $output;
    }
}
