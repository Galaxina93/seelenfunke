<?php
// app/Console/Commands/GenerateAgentBook.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Barryvdh\DomPDF\Facade\Pdf;

class GenerateAgentBook extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:agent-book';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rendert das Ebook "245 Seiten Praxiswissen - KI Agenten Management" als hochwertiges PDF und speichert es.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("Starte Generierung des Ebooks '245 Seiten Praxiswissen - KI Agenten Management'...");

        $this->line("Rendere Blade-View 'global.pdf.agenten-buch' via DomPDF...");
        
        // Render view
        $pdf = Pdf::loadView('global.pdf.agenten-buch', [])
                  ->setPaper('a4', 'portrait')
                  ->setWarnings(false)
                  ->setOption('isPhpEnabled', true);

        $this->line("Sichere Speicherort...");
        $targetDir = public_path('downloads');
        if (!File::exists($targetDir)) {
            File::makeDirectory($targetDir, 0755, true);
        }

        $filename = '245_Seiten_Praxiswissen_KI_Agenten_Management.pdf';
        $targetPath = $targetDir . '/' . $filename;

        $this->line("Schreibe PDF-Datei nach: " . $targetPath);
        File::put($targetPath, $pdf->output());

        $this->info("Ebook erfolgreich generiert!");
        $this->line("Öffentlicher Link: /downloads/" . $filename);
    }
}
