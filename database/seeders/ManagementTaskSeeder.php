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
                    ['title' => '4-6 mega geile Produktbilder und 1 Video pro Produkt generieren (Auch mit Geschenkebox) - Made in Germany hinzufügen', 'priority' => 'medium'],
                    [
                        'title' => 'Aufpreis-Extras, Verpackungs-Upgrades & Bundle-Optionen: Hier muss das Bild der Geschenkebox geändert werden und das Geschenkpapier muss rein (Echte Fotos, keine KI)',
                        'priority' => 'high',
                        'ai_plan' => 'In der Produktverwaltung unter "Aufpreis-Extras, Verpackungs-Upgrades & Bundle-Optionen" (sowie im ProductSeeder) das bisherige KI-/Platzhalterbild der Geschenkbox durch ein echtes Produktfoto der schwarzen Westpack-Schmuckbox ersetzen und das wendbare Westpack-Geschenkpapier (Nr. 0751) ebenfalls mit einem echten Foto (keine KI-Generierung) hinterlegen.',
                    ],
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
                    ['title' => 'Fragebogen zur steuerlichen Erfassung (ELSTER) ausfüllen -> Betriebliche Steuernummer & USt-IdNr. beantragen (https://www.elster.de)', 'priority' => 'high'],
                    [
                        'title' => 'Sobald USt-IdNr. (DE...) vorliegt: Westpack Geschenkpapier Nr. 0751 - Wendbar (1x 30 cm × 100 m, Art.-Nr. 03307510300, 67,90 € netto) per Reverse-Charge bestellen -> https://www.westpack.com/eur_eu_ger/wrapping-paper-n-0751-reversible-03307510200.html',
                        'priority' => 'high',
                        'ai_plan' => 'WICHTIG: Erst bestellen, sobald die deutsche USt-IdNr. vorliegt! Westpack sitzt in Dänemark und berechnet ohne USt-IdNr. 25 % dänische MwSt. (16,98 €), die das deutsche Finanzamt NICHT über die reguläre Umsatzsteuervoranmeldung erstattet. Mit gültiger USt-IdNr. greift Reverse-Charge (0,00 € MwSt., nur 67,90 € netto). Die 30 cm Rolle deckt im 50 cm Untertischabroller sowohl kleine Schmuckboxen (quer abreißen) als auch große Kristall-Boxen ab.',
                    ],
                    [
                        'title' => 'Sobald USt-IdNr. (DE...) vorliegt: Bei Westpack (sales@westpack.com) für Order #29000069521 (96x schwarze Schmuckboxen, 145,95 € brutto) nachträgliche Reverse-Charge-Rechnungskorrektur & Erstattung der 29,19 € dänischen MwSt. anfragen (Fallback: 145,95 € brutto nach § 9b EStG voll in EÜR absetzen)',
                        'priority' => 'high',
                        'ai_plan' => '1. E-Mail an sales@westpack.com senden mit Angabe von Order #29000069521 (Order-ID: 485928 vom 02.11.2026) und der neuen deutschen USt-IdNr. (DE...): Höflich um nachträgliche Umschreibung auf innergemeinschaftliche Lieferung (Reverse-Charge, 0 % MwSt.) und Rückerstattung der gezahlten 29,19 € dänischen MwSt. (25 %) bitten. 2. Falls Westpack rückwirkend nicht korrigiert: Den Beleg in der Buchhaltung auf 0 % Vorsteuerabzug stellen, damit die vollen 145,95 € brutto nach § 9b Abs. 1 EStG zu 100 % als gewinnmindernde Betriebsausgabe in die EÜR einfließen (da ausländische EU-MwSt. unter 50 € Jahresgrenze nicht über die deutsche UStVA abziehbar ist).',
                    ],
                    ['title' => 'Arbeitslosenversicherung abschließen - https://www.arbeitsagentur.de/freiwillige-arbeitslosenversicherung', 'priority' => 'medium'],
                    ['title' => 'Verpackungslizenz erwerben -> https://lucid.verpackungsregister.org/Hersteller/Registrierung/Teil-1 https://www.lizenzero.de/verpackungsmengen-kalkulator/', 'priority' => 'medium'],
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
                    ['title' => 'testdata Ordner in Storage ziehen', 'priority' => 'high'],
                    ['title' => 'blog Ordner in Storage ziehen', 'priority' => 'high'],
                    ['title' => 'env Datei anpassen', 'priority' => 'high'],
                    ['title' => 'Web Socket Einrichten (Env Files beachten Pusher Variablen, REVERB und BROADCAST_CONNECTION)', 'priority' => 'high'],
                    ['title' => 'DB Seeder die nicht gebraucht werden deaktivieren', 'priority' => 'medium'],
                    ['title' => 'DHL API Keys auf Live anpassen', 'priority' => 'high'],
                    ['title' => 'GOOGLE_PLACES_API_KEY in der Google Cloud Console auf den Live Server anpassen (Domain-Einschränkungen einrichten)', 'priority' => 'high'],
                    ['title' => 'Google Bewertungen implementieren - TEMPORÄRER TEST-MODUS', 'priority' => 'medium'],
                    ['title' => 'Die Archivrechnungen entfernen für einen cleanen start', 'priority' => 'high'],
                    [
                        'title' => 'Beim echten Livegang die Produkte die ersten 14–30 Tage zum regulären Basispreis laufen lassen und danach den Angebots-Autopiloten scharf schalten – damit der Normalpreis nachweislich im Umlauf war (§ 11 PAngV / 30-Tage-Bestpreis)',
                        'priority' => 'high',
                        'ai_plan' => 'Vor dem Livegang unter /admin/newsletter den Switch "Angebots-Autopilot" auf INAKTIV setzen, damit alle Produkte zunächst 14–30 Tage zum regulären Basispreis angeboten werden (Rechtssicherheit gegen Mondpreis-Abmahnungen nach § 5 UWG & § 11 PAngV). Nach 14–30 Tagen den Angebots-Autopiloten wieder aktivieren.',
                    ],
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
                    'ai_plan' => $todo['ai_plan'] ?? null,
                    'is_completed' => false,
                    'position' => $position,
                    'priority' => $todo['priority'] ?? 'niedrig',
                    'relevant_from' => '2026-08-01 00:00:00',
                    'created_at' => $taskTime,
                    'updated_at' => $taskTime,
                ];
            }

            DB::table('management_tasks')->insert($taksToInsert);
        }
    }
}
