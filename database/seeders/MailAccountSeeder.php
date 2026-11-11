<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailFolder;

class MailAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Falls nach einem migrate:fresh noch keine E-Mails in der Datenbank liegen,
        // automatisch Postfächer (inkl. verschlüsselter Passwörter & UUIDs), Ordner, Regeln,
        // Nachrichten und Anhänge aus dem lokalen System-Backup wiederherstellen.
        if (\App\Models\Management\Mail\MailMessage::count() === 0) {
            $backupFiles = glob(storage_path('app/system/Mein-Seelenfunke-db-backup/*.zip')) ?: [];
            rsort($backupFiles);
            foreach ($backupFiles as $zipPath) {
                $zip = new \ZipArchive();
                if ($zip->open($zipPath) === true) {
                    $sql = (string) $zip->getFromIndex(0);
                    $zip->close();
                    if (str_contains($sql, 'INSERT INTO `mail_messages`')) {
                        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
                        $pdo->exec('SET NAMES utf8mb4');
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
                        foreach (['mail_accounts', 'mail_folders', 'mail_rules', 'mail_messages', 'mail_attachments'] as $tbl) {
                            $startMarker = "/*!40000 ALTER TABLE `{$tbl}` DISABLE KEYS */;";
                            $endMarker = "/*!40000 ALTER TABLE `{$tbl}` ENABLE KEYS */;";
                            $posStart = strpos($sql, $startMarker);
                            if ($posStart !== false) {
                                $posStart += strlen($startMarker);
                                $posEnd = strpos($sql, $endMarker, $posStart);
                                if ($posEnd !== false) {
                                    $stmt = trim(substr($sql, $posStart, $posEnd - $posStart));
                                    if ($stmt !== '') {
                                        $pdo->exec('TRUNCATE TABLE `' . $tbl . '`');
                                        $pdo->exec($stmt);
                                    }
                                }
                            }
                        }
                        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
                        break;
                    }
                }
            }
        }

        $accounts = [
            [
                'name' => 'kontakt@mein-seelenfunke.de',
                'email' => 'kontakt@mein-seelenfunke.de',
                'imap_host' => 'mail.agenturserver.de',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'mail.agenturserver.de',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'is_default' => true,
                'is_commercial' => true,
                'folders' => ['Bankkonto', 'Glücksfieber', 'Hosting', 'KartonEu', 'Rechnungen', 'Rechtliches', 'Server-Backups', 'Westpack', 'XTool'],
            ],
            [
                'name' => 'kontakt@steinhauer-invest.de',
                'email' => 'kontakt@steinhauer-invest.de',
                'imap_host' => 'mail.agenturserver.de',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'mail.agenturserver.de',
                'smtp_port' => 587,
                'smtp_encryption' => 'tls',
                'is_default' => false,
                'is_commercial' => true,
                'folders' => ['Objego'],
            ],
            [
                'name' => 'alina.stone@t-online.de',
                'email' => 'alina.stone@t-online.de',
                'imap_host' => 'secureimap.t-online.de',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'securesmtp.t-online.de',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'is_default' => false,
                'is_commercial' => false,
                'folders' => ['Agentur für Arbeit', 'Amazon', 'ARAG', 'Bamtoo Zahnbürste', 'DHL', 'Google', 'HP', 'Immobilien', 'Paypal', 'Smartmiete', 'Smartsteuer', 'SOS Sanierung', 'Stefan Klink', 'Telekom', 'Transition', 'Haftpflicht', 'Krankenkasse', 'Zahnzusatz'],
            ],
            [
                'name' => 'sarcastoman@gmail.com',
                'email' => 'sarcastoman@gmail.com',
                'imap_host' => 'imap.gmail.com',
                'imap_port' => 993,
                'imap_encryption' => 'ssl',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 465,
                'smtp_encryption' => 'ssl',
                'is_default' => false,
                'is_commercial' => false,
                'folders' => ['Google', 'Transition'],
            ],
        ];

        foreach ($accounts as $accData) {
            $existingAcc = MailAccount::where('email', $accData['email'])->first();
            $fallbackPassword = $accData['email'] === 'kontakt@mein-seelenfunke.de'
                ? (string) env('MAIL_PASSWORD', '')
                : '';

            $updatePayload = [
                'name' => $accData['name'],
                'imap_host' => $accData['imap_host'],
                'imap_port' => $accData['imap_port'],
                'imap_encryption' => $accData['imap_encryption'],
                'imap_username' => $accData['email'],
                'smtp_host' => $accData['smtp_host'],
                'smtp_port' => $accData['smtp_port'],
                'smtp_encryption' => $accData['smtp_encryption'],
                'smtp_username' => $accData['email'],
                'is_default' => $accData['is_default'],
                'is_commercial' => $accData['is_commercial'],
                'status' => 'connected',
                'last_sync_at' => now(),
            ];

            if (!$existingAcc) {
                $updatePayload['password'] = $fallbackPassword;
            } elseif ($fallbackPassword !== '' && trim((string) $existingAcc->password) === '') {
                $updatePayload['password'] = $fallbackPassword;
            }

            $acc = MailAccount::updateOrCreate(
                ['email' => $accData['email']],
                $updatePayload
            );

            foreach ($accData['folders'] as $fName) {
                MailFolder::firstOrCreate([
                    'mail_account_id' => $acc->id,
                    'name' => $fName,
                ]);
            }
        }

        // Ehemalige Zusatz-Postfächer auf kontakt@mein-seelenfunke.de zusammenführen und entfernen
        $mainAcc = MailAccount::where('email', 'kontakt@mein-seelenfunke.de')->first();
        if ($mainAcc) {
            $deprecatedIds = MailAccount::whereIn('email', [
                'rechnung@mein-seelenfunke.de',
                'rechnungen@mein-seelenfunke.de',
                'impressum@mein-seelenfunke.de',
                'backup@mein-seelenfunke.de',
            ])->pluck('id')->all();

            if (!empty($deprecatedIds)) {
                \App\Models\Management\Mail\MailMessage::whereIn('mail_account_id', $deprecatedIds)
                    ->update(['mail_account_id' => $mainAcc->id]);
                MailFolder::whereIn('mail_account_id', $deprecatedIds)->delete();
                MailAccount::whereIn('id', $deprecatedIds)->delete();
            }
        }
    }
}
