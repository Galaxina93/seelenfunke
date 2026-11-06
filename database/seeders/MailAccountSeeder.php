<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Management\Mail\MailAccount;
use App\Models\Management\Mail\MailFolder;

class MailAccountSeeder extends Seeder
{
    public function run(): void
    {
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
            $acc = MailAccount::updateOrCreate(
                ['email' => $accData['email']],
                [
                    'name' => $accData['name'],
                    'password' => '',
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
                ]
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
