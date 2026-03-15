<?php

namespace App\Console\Commands;

use App\Mail\AutomaticNewsletterMail;
use App\Models\Global\GlobalLog;
use App\Models\Newsletter\Newsletter;
use App\Models\Newsletter\NewsletterSubscriber;
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
            $templates = Newsletter::where('is_active', true)->get();
            $this->info("✅ " . $templates->count() . " aktive(s) Template(s) gefunden.\n");
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
        foreach ($templates as $template) {
            $this->line("-----------------------------------------");
            $this->line("🔍 Prüfe Template: <comment>{$template->title}</comment> (Event: {$template->target_event_key})");

            $targetDate = $this->getHolidayDate($template->target_event_key, $year);
            $sendDate = $targetDate->copy()->subDays($template->days_offset);

            // Logging der berechneten Daten im Terminal
            $this->line("   - Ziel-Datum des Events: " . $targetDate->format('d.m.Y'));
            $this->line("   - Eingestellter Offset:  {$template->days_offset} Tage vorher");
            $this->line("   - Berechnetes Sendedatum: " . $sendDate->format('d.m.Y'));

            // 3. Prüfen, ob Sendedatum = Heute ist
            if ($sendDate->isSameDay($today)) {
                $this->info("   🎯 TREFFER! Versand ist für HEUTE geplant.");

                if (class_exists(GlobalLog::class)) {
                    $log = GlobalLog::start(
                        'newsletter:send',
                        "Newsletter-Kampagne: {$template->title}",
                        'system'
                    );
                }

                try {
                    $this->line("   ⏳ Starte Batch-Versand an verifizierte Abonnenten...");

                    $count = $this->sendBatch($template);

                    $this->info("   ✅ Erfolgreich {$count} Mails in die Warteschlange (Queue) geschoben.");

                    if (isset($log)) {
                        $log->finish(
                            'success',
                            "Der Newsletter wurde erfolgreich an $count Abonnenten in die Warteschlange gestellt.",
                            ['template_id' => $template->id, 'sent_count' => $count]
                        );
                    }

                } catch (\Exception $e) {
                    $this->error("   ❌ Fehler beim Versand: " . $e->getMessage());
                    if (isset($log)) {
                        $log->finish('error', 'Fehler beim Versand: ' . $e->getMessage());
                    }
                }
            } else {
                $this->line("   ⏭️ Wird übersprungen (Datum stimmt nicht überein).");
            }
        }

        $this->info("\n🎉 Prüfung vollständig abgeschlossen.");
        return 0;
    }

    /**
     * Versendet den Newsletter an alle verifizierten Abonnenten über die Queue.
     */
    private function sendBatch(Newsletter $template): int
    {
        Log::info("Newsletter Command: Starte Batch-Versand für '{$template->subject}'");

        $subscribers = NewsletterSubscriber::where('is_verified', true)->get();
        $count = 0;

        foreach ($subscribers as $subscriber) {
            // Die Mail wird via ->queue() asynchron im Hintergrund versendet!
            Mail::to($subscriber->email)->queue(new AutomaticNewsletterMail($template, $subscriber));
            $count++;
        }

        return $count;
    }

    /**
     * Gibt das Datum für ein bestimmtes Ereignis in einem bestimmten Jahr zurück.
     */
    private function getHolidayDate($key, $year): Carbon
    {
        switch ($key) {
            case 'valentines': return Carbon::create($year, 2, 14);
            case 'womens_day': return Carbon::create($year, 3, 8);
            case 'halloween': return Carbon::create($year, 10, 31);
            case 'christmas': return Carbon::create($year, 12, 24);
            case 'new_year': return Carbon::create($year, 1, 1);
            case 'sale_summer': return Carbon::create($year, 7, 1);
            case 'sale_winter': return Carbon::create($year, 1, 1);
            case 'easter': return $this->getEasterDate($year);
            case 'mothers_day': return Carbon::create($year, 5, 1)->nthOfMonth(2, Carbon::SUNDAY);
            case 'fathers_day': return $this->getEasterDate($year)->addDays(39);
            case 'advent_1': return Carbon::create($year, 11, 26)->next(Carbon::SUNDAY);
            default: return Carbon::now();
        }
    }

    /**
     * Berechnet den Ostersonntag für ein gegebenes Jahr (ohne PHP Calendar Extension)
     */
    private function getEasterDate($year): Carbon
    {
        $K = (int)($year / 100);
        $M = 15 + (int)((3 * $K + 3) / 4) - (int)((8 * $K + 13) / 25);
        $S = 2 - (int)((3 * $K + 3) / 4);
        $A = $year % 19;
        $D = (19 * $A + $M) % 30;
        $R = (int)($D / 29) + ((int)($D / 28) - (int)($D / 29)) * (int)($A / 11);
        $OG = 21 + $D - $R;
        $SZ = 7 - ($year + (int)($year / 4) + $S) % 7;
        $OE = 7 - ($OG - $SZ) % 7;

        return Carbon::createFromDate($year, 3, 1)->addDays($OG + $OE - 1);
    }
}
