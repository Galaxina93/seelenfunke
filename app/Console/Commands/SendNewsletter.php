<?php

namespace App\Console\Commands;

use App\Mail\NewsletterAutomaticMail;
use App\Models\Marketing\MarketingNewsletter;
use App\Models\Marketing\MarketingNewsletterSubscriber;
use App\Models\System\SystemLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNewsletter extends Command
{
    // Signatur an deinen ausgeführten Befehl angepasst
    protected $signature = 'send-newsletters';
    protected $description = 'Prüft auf anstehende Newsletter und versendet diese (mit detailliertem Output).';

    public function handle()
    {
        $today = Carbon::today();
        $year = $today->year;

        $this->info("=========================================");
        $this->info("🚀 Starte Newsletter-Automation");
        $this->info("📅 Heutiges Datum: " . $today->format('d.m.Y'));
        $this->info("=========================================\n");

        // 1. Datenbank-Verbindung prüfen und Templates laden
        try {
            $this->line("⏳ Verbinde mit der Datenbank und lade aktive Templates...");
            $templates = MarketingNewsletter::where('is_active', true)->get();
            $this->info("✅ " . $templates->count() . " aktive(s) Template(s) gefunden.");

            // Synchronisiere zuerst den 100% Angebots-Autopiloten für die Feiertage
            $offerSync = app(\App\Services\Marketing\HolidayOfferAutopilotService::class)->syncActiveOffers($today);
            $this->line("🏷️  Angebots-Autopilot abgeglichen (Aktualisiert: {$offerSync['updated_count']}, Zurückgesetzt: {$offerSync['restored_count']})\n");
        } catch (QueryException $e) {
            $this->error("❌ FATALER FEHLER: Datenbankverbindung fehlgeschlagen!");
            $this->line("Detail: " . $e->getMessage());
            $this->warn("\n💡 TIPP: Nutzt du Docker (z.B. Sail)? Dann läuft die Datenbank isoliert.");
            $this->warn("Führe den Befehl im Container aus, z.B. über:");
            $this->warn("👉 funki artisan send-newsletters");
            $this->warn("👉 ./vendor/bin/sail artisan send-newsletters");
            return 1; // Abbruch mit Fehlercode
        }

        // 2. Templates durchlaufen
        $hasErrors = false;
        foreach ($templates as $template) {
            $this->line("-----------------------------------------");
            $this->line("🔍 Prüfe Template: <comment>{$template->title}</comment> (Typ: " . ($template->type ?? 'automated') . ", Event: {$template->target_event_key})");

            if (($template->type ?? 'automated') === 'manual') {
                if (empty($template->send_at)) {
                    $this->line("   ⏭️ Wird übersprungen (Manuelle Kampagne ohne Sendedatum).");
                    continue;
                }
                $sendDate = Carbon::parse($template->send_at);
                $this->line("   - Geplantes Sendedatum: " . $sendDate->format('d.m.Y H:i'));
            } else {
                $targetDate = null;
                $sendDate = null;
                foreach ([$year, $year + 1, $year - 1] as $candidateYear) {
                    $candidateTarget = $this->getHolidayDate($template->target_event_key, $candidateYear);
                    if (!$candidateTarget) {
                        continue;
                    }
                    $candidateSend = $candidateTarget->copy()->subDays((int) $template->days_offset);
                    if ($targetDate === null) {
                        $targetDate = $candidateTarget;
                        $sendDate = $candidateSend;
                    }
                    if ($candidateSend->isSameDay($today)) {
                        $targetDate = $candidateTarget;
                        $sendDate = $candidateSend;
                        break;
                    }
                }
                if (!$targetDate || !$sendDate) {
                    $this->line("   ⏭️ Wird übersprungen (Unbekanntes Ereignis-Key).");
                    continue;
                }

                // Logging der berechneten Daten im Terminal
                $this->line("   - Ziel-Datum des Events: " . $targetDate->format('d.m.Y'));
                $this->line("   - Eingestellter Offset:  {$template->days_offset} Tage vorher");
                $this->line("   - Berechnetes Sendedatum: " . $sendDate->format('d.m.Y'));
            }

            // 3. Prüfen, ob Sendedatum = Heute ist (bei manuellen Kampagnen zusätzlich prüfen, dass der geplante Zeitpunkt bereits erreicht wurde)
            $isManualFuture = (($template->type ?? 'automated') === 'manual') && $sendDate->isFuture();
            if ($sendDate->isSameDay($today) && !$isManualFuture) {
                if (class_exists(SystemLog::class) && SystemLog::where('action_id', 'newsletter:send')
                    ->where('status', 'success')
                    ->whereDate('created_at', $today)
                    ->where('payload->template_id', $template->id)
                    ->exists()) {
                    $this->line("   ⏭️ Wird übersprungen (Bereits heute versendet).");
                    continue;
                }

                $this->info("   🎯 TREFFER! Versand ist für HEUTE geplant.");

                if (class_exists(SystemLog::class)) {
                    $log = SystemLog::start(
                        'newsletter:send',
                        "Newsletter-Kampagne: {$template->title}",
                        'system'
                    );
                }

                try {
                    $this->line("   ⏳ Starte Batch-Versand an verifizierte Abonnenten...");

                    $count = $this->sendBatch($template);

                    // Manuelle Einmal-Kampagnen nach erfolgreichem Versand archivieren
                    if (($template->type ?? 'automated') === 'manual') {
                        $template->update(['is_active' => false]);
                    }

                    $this->info("   ✅ Erfolgreich {$count} Mails in die Warteschlange (Queue) geschoben.");

                    if (isset($log)) {
                        $log->finish(
                            'success',
                            "Der Newsletter wurde erfolgreich an $count Abonnenten in die Warteschlange gestellt.",
                            ['template_id' => $template->id, 'sent_count' => $count]
                        );
                    }
                    \App\Services\System\SystemAlertTicketService::autoResolveByAlertPrefix(
                        'newsletter_send_failed:' . $template->id,
                        "Newsletter-Kampagne '{$template->title}' wurde erfolgreich an {$count} Abonnenten eingereiht.",
                        'Marketi'
                    );

                } catch (\Throwable $e) {
                    $hasErrors = true;
                    $this->error("   ❌ Fehler beim Versand: " . $e->getMessage());
                    Log::error("Newsletter-Versand fehlgeschlagen ({$template->title}): " . $e->getMessage(), ['exception' => $e]);
                    if (isset($log)) {
                        $log->finish('error', 'Fehler beim Versand: ' . $e->getMessage());
                    }
                    \App\Services\System\SystemAlertTicketService::upsertSystemTicket(
                        alertKey: 'newsletter_send_failed:' . $template->id,
                        title: "Newsletter-Versand fehlgeschlagen: {$template->title}",
                        markdownBody: "Beim automatischen Versand der Kampagne `{$template->title}` ist ein Fehler aufgetreten:\n\n" . $e->getMessage(),
                        departmentName: 'Marketing',
                        agentName: 'Marketi',
                        ticketType: 'marketing_alert',
                        priority: 'high',
                        assignToAntigravity: true
                    );
                }
            } else {
                $this->line("   ⏭️ Wird übersprungen (Datum stimmt nicht überein).");
            }
        }

        $this->info("\n🎉 Prüfung vollständig abgeschlossen.");
        return $hasErrors ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Versendet den Newsletter speicherschonend in 200er-Chunks an alle verifizierten Abonnenten über die Queue.
     */
    private function sendBatch(MarketingNewsletter $template): int
    {
        Log::info("Newsletter Command: Starte Batch-Versand für '{$template->subject}'");

        $query = MarketingNewsletterSubscriber::where('is_verified', true);
        if (\Illuminate\Support\Facades\Schema::hasColumn('marketing_newsletter_subscribers', 'receive_newsletter')) {
            $query->where(function ($q) {
                $q->where('receive_newsletter', true)->orWhereNull('receive_newsletter');
            });
        }

        $count = 0;
        $query->chunkById(200, function ($subscribers) use ($template, &$count) {
            foreach ($subscribers as $subscriber) {
                $email = trim((string) ($subscriber->email ?? ''));
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                try {
                    Mail::to($email)->queue(new NewsletterAutomaticMail($template, $subscriber));
                    $count++;
                } catch (\Throwable $mailEx) {
                    Log::warning("Newsletter-Queue für {$email} übersprungen: " . $mailEx->getMessage());
                }
            }
        });

        return $count;
    }

    /**
     * Nutzt zentral den HolidayOfferAutopilotService als Single Source of Truth für alle Feiertags- & Sale-Daten.
     */
    private function getHolidayDate($key, $year): ?Carbon
    {
        if (!is_string($key) || trim($key) === '') {
            return null;
        }

        return app(\App\Services\Marketing\HolidayOfferAutopilotService::class)->getHolidayDate(trim($key), (int) $year);
    }
}
