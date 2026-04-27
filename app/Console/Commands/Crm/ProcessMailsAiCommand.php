<?php

namespace App\Console\Commands\Crm;

use Illuminate\Console\Command;
use App\Models\Management\Mail\MailMessage;
use App\Models\Ai\AiAgent;
use App\Services\AI\GeminiAgent;

class ProcessMailsAiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:ai-process-mails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lässt die KI autonom neue E-Mails lesen, bewerten, sortieren und ggf. beantworten.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Suche nach dem KI-Mail-Agenten...');
        
        // Dynamisch den Agenten mit der Rolle "Supporter" suchen
        $role = \App\Models\Ai\AiRole::where('name', 'Supporter')->first();
        if (!$role) {
            $this->error('Keine Supporter-Rolle gefunden.');
            return;
        }

        $agent = AiAgent::where('ai_role_id', $role->id)->first();
        if (!$agent) {
            $this->error('Kein Agent mit der Supporter-Rolle gefunden.');
            return;
        }

        $this->info("Nutze Agent {$agent->name} für die E-Mail-Verarbeitung.");

        $maxCycles = 50;
        $cycle = 1;

        while ($cycle <= $maxCycles) {
            $mails = MailMessage::where('folder', 'INBOX')
                ->orderBy('received_at', 'asc')
                ->limit(15)
                ->get();

            if ($mails->isEmpty()) {
                $this->info('Alle Mails wurden erfolgreich von der KI abgearbeitet. Inbox Zero erreicht!');
                break;
            }

            $pendingCount = MailMessage::where('folder', 'INBOX')->count();
            $this->info("Zyklus {$cycle}: {$pendingCount} Mails verbleibend. Analysiere einen Batch von {$mails->count()} Mails auf einmal...");

            $mailData = [];
            foreach ($mails as $mail) {
                $content = strip_tags($mail->body_html) ?: $mail->body_plain;
                $mailData[] = [
                    'id' => $mail->id,
                    'from' => "{$mail->from_name} <{$mail->from_email}>",
                    'subject' => $mail->subject,
                    'received_at' => clone $mail->received_at, // just formatting it to string implicitly
                    'content' => substr($content, 0, 1500) // Begrenzt auf 1500 Zeichen pro Mail
                ];
            }

            $mailsJson = json_encode($mailData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            $prompt = "Du bist das autonome KI-Sekretariat für das Seelenfunke E-Mail-Postfach.
Hier ist ein Batch von {$mails->count()} neuen E-Mails im JSON-Format:

{$mailsJson}

DEINE AUFGABE:
Nutze ZWINGEND UND NUR EINMAL das Tool 'mail_bulk_update', um ALLE diese Mails auf einmal abzufertigen!
Dein oberstes Ziel ist INBOX ZERO. Keine einzige Mail darf im Posteingang (INBOX) verbleiben!

Erstelle für das Tool ein 'updates' Array, welches für jede der E-Mails ein Objekt mit diesen Feldern enthält:
- email_id
- target_folder: Du DARFST NICHT 'INBOX' verwenden! Erschaffe logische Ordner-Namen (z.B. 'Support-Tickets', 'Kundenanfragen', 'Rechnungen', 'Zur Prüfung' (für Mails, die ein Mensch sehen muss), 'Spam', 'Archive' (für Info-Mails ohne Handlungsbedarf)). Die Ordner werden bei Bedarf automatisch vom System angelegt!
- priority (low, normal, high)
- category (z.B. Rechnung, Anfrage, Support, Spam, Warnung)
- tags (Ein Array mit sinnvollen Schlagworten)
- ai_status (Setze 'processed' für Mails die komplett erledigt sind. Setze 'needs_human_review', falls ein Mensch zwingend eingreifen oder antworten muss!)

Formuliere nur das 'updates' Array, rufe das Tool 'mail_bulk_update' exakt 1x auf, und sag mir danach mit einem Satz, dass du fertig bist.";

            $gemini = new GeminiAgent($agent);
            $response = $gemini->ask([
                ['role' => 'user', 'content' => $prompt]
            ]);

            $this->line("KI-Antwort (Zyklus {$cycle}): " . ($response['response'] ?? 'Keine Antwort.'));
            
            $cycle++;
            sleep(2); // Kurze Pause zwischen den Zyklen
        }

        if ($cycle > $maxCycles) {
            $this->warn('Maximales Zyklus-Limit erreicht. Es sind womöglich noch ungelöste Mails übrig.');
        }
    }
}
