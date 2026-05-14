<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\Functions\AiMailFuncs;
use App\Services\AI\Functions\AiHolidayPlannerFuncs;
use App\Services\AI\Functions\AiPersonaFuncs;
use App\Services\AI\Functions\AiMapControlFuncs;
use Illuminate\Support\Facades\Auth;
use App\Models\System\SystemUser;

class TestAiMailsCommand extends Command
{
    use AiMailFuncs, AiHolidayPlannerFuncs, AiPersonaFuncs, AiMapControlFuncs;

    protected $signature = 'test:ai-mails {email?}';
    protected $description = 'Testet alle AI-gestützten Mail- und PDF-Funktionen';

    public function handle()
    {
        $testEmail = $this->argument('email') ?: 'kontakt@mein-seelenfunke.de';

        $this->info("Starte unabhängigen AI Mail & PDF Test an: {$testEmail}");
        $this->line("=========================================================\n");

        $mockAgent = (object)['name' => 'Agent Test-Bot 007'];
        session(['current_ai_agent_name' => 'Agent Test-Bot 007']);



        $this->info("INFO: Skript startet. Der Versand von 5 echten E-Mails über SMTP kann 30-60 Sekunden dauern. Bitte Geduld...");

        $this->info("1. Teste AiMailFuncs (Seelenfunke Design)...");
        try {
            $res1 = self::executeSendEmail([
                'recipient' => $testEmail,
                'subject' => '[Test] Firmen-Design Mail',
                'body' => 'Das ist eine Testnachricht im offiziellen Seelenfunke Corporate Design.',
                'design' => 'seelenfunke'
            ], $mockAgent);
            $this->line("Ergebnis: " . json_encode($res1) . "\n");
        } catch (\Exception $e) {
            $this->error("FEHLER BEI 1: " . $e->getMessage());
        }

        // 2. Test: AiMailFuncs (Generic Design)
        $this->info("2. Teste AiMailFuncs (Generic Design)...");
        $res2 = self::executeSendEmail([
            'recipient' => $testEmail,
            'subject' => '[Test] Generic-Design Mail',
            'body' => 'Das ist eine Testnachricht im neutralen (Generic) Design für interne Berichte.',
            'design' => 'generic'
        ], $mockAgent);
        $this->line("Ergebnis: " . json_encode($res2) . "\n");

        // 3. Test: AiHolidayPlannerFuncs (PDF & Mail)
        $this->info("3. Teste Holiday Planner (Urlaubs-PDF & Mail)...");
        $res3 = self::executeHolidayGeneratePdfPlan([
            'title' => 'Test-Wochenende in Rom',
            'description' => 'Ein schöner Kurztrip zum Testen der PDF-Generierung.',
            'start_date' => '01.06.2026',
            'end_date' => '05.06.2026',
            'travel_logistics' => ['start_address' => 'Berlin', 'destination_address' => 'Rom'],
            'trip_specific_packing_items' => ['Reiseadapter', 'Sonnenbrille'],
            'itinerary' => [],
            'general_tips' => ['Viel Pizza essen!'],
            'target_action' => 'email',
            'recipient_email' => $testEmail
        ], $mockAgent);
        $this->line("Ergebnis: " . json_encode($res3) . "\n");

        // 4. Test: AiPersonaFuncs (OSINT Profil-PDF & Mail)
        $this->info("4. Teste Persona Funcs (Dossier-PDF & Mail)...");
        $res4 = self::executePersonaGeneratePdf([
            'name' => 'Max Mustermann',
            'summary' => 'Dies ist ein Testprofil für die OSINT Akte.',
            'target_action' => 'email',
            'recipient_email' => $testEmail
        ], $mockAgent);
        $this->line("Ergebnis: " . json_encode($res4) . "\n");

        // 5. Test: AiMapControlFuncs (Map Summary PDF & Mail)
        $this->info("5. Teste Map Control (Map Summary PDF & Mail)...");
        $res5 = self::executeMapGeneratePdfSummary([
            'title' => 'Test Standort Analyse',
            'summary' => 'Hier ist eine kurze Standortzusammenfassung.',
            'send_email' => true,
            'email_address' => $testEmail
        ], $mockAgent);
        $this->line("Ergebnis: " . json_encode($res5) . "\n");

        $this->info("=========================================================");
        $this->info("Test abgeschlossen. Bitte Postfach prüfen!");
    }
}
