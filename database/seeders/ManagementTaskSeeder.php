<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementTaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        $phases = [
            [
                'list' => [
                    'name' => 'PHASE 1 - PRODUKTE',
                    'icon' => 'cube',
                    'color' => '#E53E3E',
                ],
                'taks' => [
                    ['title' => 'Laser Schulung durchführen', 'priority' => 'high'],
                    ['title' => '4-6 mega geile Produktbilder und 1 Video pro Produkt generieren (Auch mit Geschenkebox) - Made in Germany hinzufügen', 'priority' => 'medium'],
                    ['title' => 'Produktbild mit Maßangabe pro Produkt', 'priority' => 'medium'],
                    ['title' => 'Produktvorlagen erstellen', 'priority' => 'medium'],
                    ['title' => 'Landing Pages umfangreich und fett aufbauen', 'priority' => 'high'],
                ]
            ],
            [
                'list' => [
                    'name' => 'PHASE 2 - MARKETING',
                    'icon' => 'speaker-wave',
                    'color' => '#D69E2E',
                ],
                'taks' => [
                    ['title' => 'Zum Start des neuen Instagram Accounts das Handy in eine Schleife tun und aufmachen. Filmen und Instagram Seite auf den Handy offen haben (zweit Handy nutzen)', 'priority' => 'medium'],
                    ['title' => 'ETSY nur als Booster nutzen und auf Produkt Konfigurator weiterleiten', 'priority' => 'high'],
                    ['title' => 'QR Code, Insta, tiktok mit ins Paket legen', 'priority' => 'medium'],
                ]
            ],
            [
                'list' => [
                    'name' => 'PHASE 3 - MAIN ABLAUF',
                    'icon' => 'flag',
                    'color' => '#3182CE',
                ],
                'taks' => [
                    ['title' => 'Krankenkasse bescheid geben Selbständigkeit ab Mitte Februar spätestens 15.07.2026', 'priority' => 'high'],
                    ['title' => 'Gewerbe anmelden https://gewerbe.buergerdienste-online.de/webclient/app/m/3151009/gewerbeanzeige?startPage=0', 'priority' => 'high'],
                    ['title' => 'Konto bei Finom eröffnen -> https://app.finom.co/de/signup/?fnm_product=business&source=finom.de&utm_source=google&utm_medium=cpc&utm_campaign=search-tofu-banking-brand-de-germany-10209387005&utm_content=cid%7C10209387005%7Cgid%7C181322099254%7Caid%7C7644', 'priority' => 'high'],
                    ['title' => 'Laserschulung durchführen -> https://luminus-laserschutz.de/kurse/laserschutzbeauftragter-industrie-gewerbe/?_gl=1*tzjq9u*_up*MQ..*_gs*MQ..&gclid=Cj0KCQiAo4TKBhDRARIsAGW29bcTods5PR5OMiWtKsHmbh-TmpQfEYU4HeXs2Sw_lWt40yYZRhrkot4aAqSfEALw_wcB&gbra', 'priority' => 'high'],
                    ['title' => 'Marke schützen -> https://direkt.dpma.de/DpmaDirektWebEditoren/w7005/w7005web.xhtml?jftfdi=&jffi=w7005&jfwid=64cea193-7eb1-4028-ac97-7d08a38df852:0', 'priority' => 'medium'],
                    ['title' => 'Versicherung abschließen Versicherungen für Gründer: online absichern | Hiscox Tätigkeit: E-Commerce, Händler von Büro ... https://direkt-versicherung.hiscox.de/antrag/Angebot?ProductId=H-DPD-PSC-1-VersicherungfuerShops', 'priority' => 'high'],
                    ['title' => 'Arbeitslosenversicherung abschließen - https://www.arbeitsagentur.de/freiwillige-arbeitslosenversicherung', 'priority' => 'medium'],
                    ['title' => 'Verpackungslizenz erwerben -> https://lucid.verpackungsregister.org/Hersteller/Registrierung/Teil-1 https://www.lizenzero.de/verpackungsmengen-kalkulator/', 'priority' => 'medium'],
                    ['title' => 'Google Business Account einrichten', 'priority' => 'high'],
                    ['title' => 'Google Bewertungen aktivieren', 'priority' => 'high'],
                ]
            ],
            [
                'list' => [
                    'name' => 'PHASE 4 - LIVEGANG',
                    'icon' => 'sparkles',
                    'color' => '#805AD5',
                ],
                'taks' => [
                    ['title' => 'Webhook in Stripe anpassen (Zahlungslinks für Kunden) -> https://dashboard.stripe.com/acct_1Sttm3GnukHKxpOe/test/settings', 'priority' => 'high'],
                    ['title' => 'testdata Ordner in Storage ziehen', 'priority' => 'high'],
                    ['title' => 'blog Ordner in Storage ziehen', 'priority' => 'high'],
                    ['title' => 'env Datei anpassen', 'priority' => 'high'],
                    ['title' => 'Web Socket Einrichten (Env Files beachten Pusher Variablen, REVERB und BROADCAST_CONNECTION)', 'priority' => 'high'],
                    ['title' => 'DB Seeder die nicht gebraucht werden deaktivieren', 'priority' => 'medium'],
                    ['title' => 'DHL API Keys auf Live anpassen', 'priority' => 'high'],
                    ['title' => 'Google Bewertungen implementieren - TEMPORÄRER TEST-MODUS', 'priority' => 'medium'],
                    ['title' => 'Final prüfen -> STEUER 0.5 Punkte von Noah auf mich übertragen', 'priority' => 'high'],
                ]
            ],
        ];

        $baseNow = now();
        $phaseCount = 0;

        foreach ($phases as $phase) {
            // Liste erstellen, jede Phase 1 Minute später als die davor
            $phaseTime = $baseNow->copy()->addMinutes($phaseCount++);
            $listId = Str::uuid()->toString();

            DB::table('management_task_lists')->insert([
                'id' => $listId,
                'name' => $phase['list']['name'],
                'icon' => $phase['list']['icon'],
                'color' => $phase['list']['color'],
                'created_at' => $phaseTime,
                'updated_at' => $phaseTime,
            ]);

            // Dazugehörige taks einfügen
            $taksToInsert = [];
            $position = 0;

            foreach ($phase['taks'] as $todo) {
                // Je niedriger die subSecond, desto neuer ist der created_at,
                // also landet dieser Task bei "desc" weiter oben.
                $taskTime = $phaseTime->copy()->subSeconds($position++);

                $taksToInsert[] = [
                    'id' => Str::uuid()->toString(),
                    'task_list_id' => $listId,
                    'parent_id' => null, // Wir halten es flach, wie gewünscht
                    'title' => $todo['title'],
                    'is_completed' => false,
                    'position' => $position,
                    'priority' => 'niedrig',
                    'created_at' => $taskTime,
                    'updated_at' => $taskTime,
                ];
            }

            DB::table('management_tasks')->insert($taksToInsert);
        }
    }
}
