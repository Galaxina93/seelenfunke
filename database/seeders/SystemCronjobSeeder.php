<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\System\SystemCronjob;

class SystemCronjobSeeder extends Seeder
{
    /**
     * Liefert die Liste aller Standard-System-Cronjobs.
     */
    public static function getDefaultCronjobs(): array
    {
        return [
            [
                'name' => 'Newsletter Versand',
                'description' => 'Täglich um 08:00 Uhr prüfen und Newsletter senden',
                'command' => 'send-newsletters',
                'schedule' => '0 8 * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Seelenfunken Treue-Bonus (Geburtstag & Jahrestag)',
                'description' => 'Prüft täglich um 08:15 Uhr Geburtstage und Manufaktur-Jahrestage aktiver Seelenfunken-Mitglieder und schreibt automatisch +50 Treue-Funken inkl. E-Mail gut',
                'command' => 'gamification:award-loyalty-sparks',
                'schedule' => '15 8 * * *',
                'is_active' => true,
            ],
            [
                'name' => 'IMAP E-Mail Fetch',
                'description' => 'E-Mails via IMAP asynchron vom Server abrufen (Posteingang sync)',
                'command' => 'crm:fetch-mails',
                'schedule' => '* * * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Neujahrs Gutscheine',
                'description' => 'Automatische Gutschein-Generierung für das neue Jahr (am 1. Januar)',
                'command' => 'shop:generate-monthly-vouchers',
                'schedule' => '5 0 1 1 *',
                'is_active' => true,
            ],
            [
                'name' => 'UStVA Autopilot',
                'description' => 'Läuft am 5. jeden Monats und generiert den Steuer-Export des Vormonats',
                'command' => 'funki:generate-tax-export',
                'schedule' => '0 2 5 * *',
                'is_active' => true,
            ],
            [
                'name' => 'DHL Sendungsverfolgung',
                'description' => 'Prüft Pakete "in Zustellung" und schließt Orders automatisch ab',
                'command' => 'dhl:check-delivery-status',
                'schedule' => '0 */4 * * *',
                'is_active' => true,
            ],
            [
                'name' => 'System Herzschlag',
                'description' => 'System-Herzschlag für das Health-Dashboard (jede Minute)',
                'command' => 'system:heartbeat',
                'schedule' => '* * * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Datenbank Backup',
                'description' => 'Nächtliches Datenbank-Backup über Spatie Laravel Backup',
                'command' => 'backup:run',
                'parameters' => '--only-db',
                'schedule' => '0 3 * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Backup Bereinigung',
                'description' => 'Löscht automatisch alte Backups, die älter als 15 Tage sind',
                'command' => 'backup:clean',
                'schedule' => '30 3 * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Kapazitäts-Engine',
                'description' => 'Dynamischer Kapazitäts-Berechner und Autopilot',
                'command' => 'shop:capacity-engine',
                'schedule' => '*/5 * * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Auto-Resolve KI Chats',
                'description' => 'Schließt inaktive KI Support-Chats nach 12 Stunden automatisch',
                'command' => 'support:auto-resolve-chats',
                'schedule' => '0 * * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Warenkorb Erinnerungen',
                'description' => 'Sendet automatische E-Mail Erinnerungen für stehengelassene Warenkörbe',
                'command' => 'shop:send-abandoned-cart-reminders',
                'schedule' => '*/5 * * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Verwaiste Uploads & Warenkörbe bereinigen',
                'description' => 'Bereinigt nächtlich unbestellte Uploads/Snapshots (>48h), Temp-Zone (>24h), KI-Exporte (>7d), Chat-Uploads (>30d) & alte Warenkörbe (>14d)',
                'command' => 'shop:cleanup-orphaned-uploads',
                'schedule' => '0 4 * * *',
                'is_active' => true,
            ],
            [
                'name' => 'Unverifizierte Benutzer bereinigen',
                'description' => 'Löscht nächtlich unverifizierte Kunden-Accounts ohne Bestellung, die älter als 24 Stunden sind',
                'command' => 'system:delete-unverified-users',
                'schedule' => '0 0 * * *',
                'is_active' => true,
            ],
        ];
    }

    /**
     * Stellt sicher, dass alle Standard-Cronjobs in der Datenbank registriert sind,
     * ohne bestehende Admin-Anpassungen (Intervall / Aktiv-Status) zu überschreiben.
     */
    public static function ensureDefaultCronjobsExist(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('system_cronjobs')) {
            return;
        }

        foreach (self::getDefaultCronjobs() as $job) {
            SystemCronjob::firstOrCreate(
                ['command' => $job['command']],
                $job
            );
        }
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::getDefaultCronjobs() as $job) {
            SystemCronjob::updateOrCreate(
                ['command' => $job['command']],
                $job
            );
        }
    }
}
