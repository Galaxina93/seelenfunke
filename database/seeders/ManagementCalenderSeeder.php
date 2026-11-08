<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Management\ManagementCalendarEvent;
use Carbon\Carbon;

class ManagementCalenderSeeder extends Seeder
{
    public function run(): void
    {
        // Manuell geseedete Termine bereinigen (ICS-Importe wie Müllabfuhr bleiben unberührt)
        ManagementCalendarEvent::whereNull('ics_uid')->delete();

        $events = [
            [
                'title' => 'Operation / Krankenhausaufenthalt',
                'start_date' => Carbon::create(2026, 6, 23, 0, 0, 0),
                'end_date' => Carbon::create(2026, 7, 23, 23, 59, 59),
                'is_all_day' => true,
                'category' => 'general',
                'description' => 'Geplante OP mit anschließender Regenerationsphase. Keine operativen Tasks während dieser Zeit übernehmen.',
                'priority' => 'high',
            ],
            [
                'title' => 'Gewerbeanmeldung Stadt Gifhorn bescheinigt (GewA 1)',
                'start_date' => Carbon::create(2026, 10, 28, 0, 0, 0),
                'end_date' => Carbon::create(2026, 10, 28, 23, 59, 59),
                'is_all_day' => true,
                'category' => 'project',
                'description' => "Amtliche Bescheinigung GewA 1 (§ 14 / § 55c GewO) der Stadt Gifhorn für Mein-Seelenfunke ausgestellt.\n- Aktenzeichen: gew/28-10-2026/Ce/402\n- Vorgangsnummer: gew / 28-10-2026 / Ce / 893\n- Tätigkeitsbeginn: 01.11.2026",
                'priority' => 'high',
            ],
            [
                'title' => 'Offizieller Tätigkeitsbeginn Mein-Seelenfunke',
                'start_date' => Carbon::create(2026, 11, 1, 0, 0, 0),
                'end_date' => Carbon::create(2026, 11, 1, 23, 59, 59),
                'is_all_day' => true,
                'category' => 'project',
                'description' => 'Offizieller gewerblicher Start von Mein-Seelenfunke (Vorbereitungshandlungen, Laser-Manufaktur-Aufbau & Einkauf) gemäß Gewerbeanmeldung und ELSTER-Fragebogen.',
                'priority' => 'high',
            ],
            [
                'title' => 'ELSTER: Fragebogen zur steuerlichen Erfassung übermittelt',
                'start_date' => Carbon::create(2026, 11, 6, 15, 35, 0),
                'end_date' => Carbon::create(2026, 11, 6, 16, 0, 0),
                'is_all_day' => false,
                'category' => 'project',
                'description' => "Elektronisch an das Finanzamt Gifhorn übermittelt.\n- Transferticket: ep3104hvg8fm3b1m4zrwrd3djc6ieugc\n- ELSTER-Referenz: 2169193168 -B41\n- Beantragt: Neue betriebliche Steuernummer, USt-IdNr. (BZSt), Regelbesteuerung (§ 19 Abs. 3 UStG mit Vorsteuerabzug) und Istversteuerung (§ 20 UStG).\n- Hinterlegtes Geschäftskonto: FYRST (IBAN DE76 2507 0370 0079 8629 00, BIC DEUTDE2HP24).",
                'priority' => 'high',
            ],
            [
                'title' => 'Prüfen: Post vom Finanzamt Gifhorn (Betriebliche Steuernummer) da?',
                'start_date' => Carbon::create(2026, 11, 20, 10, 0, 0),
                'end_date' => Carbon::create(2026, 11, 20, 10, 30, 0),
                'is_all_day' => false,
                'reminder_minutes' => 60,
                'category' => 'project',
                'description' => "2 Wochen nach ELSTER-Übermittlung (06.11.2026, Transferticket: ep3104hvg8fm3b1m4zrwrd3djc6ieugc):\n1. Prüfen, ob das Schreiben vom Finanzamt Gifhorn mit der neuen betrieblichen Steuernummer für Mein-Seelenfunke per Post eingetroffen ist.\n2. Falls noch nicht da: Kurz telefonisch beim Finanzamt Gifhorn (Tel. 05371 800-0, Neugründungsstelle) oder über Mein ELSTER ('Sonstige Nachricht an das Finanzamt') unter Nennung des Transfertickets ep3104hvg8fm3b1m4zrwrd3djc6ieugc nach dem Bearbeitungsstand fragen.",
                'priority' => 'high',
            ],
            [
                'title' => 'Hiscox Betriebshaftpflicht: SEPA-Abbuchung Erstprämie (100,14 €)',
                'start_date' => Carbon::create(2026, 11, 22, 0, 0, 0),
                'end_date' => Carbon::create(2026, 11, 22, 23, 59, 59),
                'is_all_day' => true,
                'category' => 'general',
                'description' => 'Geplanter SEPA-Lastschrifteinzug der Erstjahresprämie (100,14 € brutto, Versicherungsschein-Nr. PL-PSC700408140/00) durch Hiscox SA.',
                'priority' => 'medium',
            ],
            [
                'title' => 'Prüfen: USt-IdNr. (DE...) vom BZSt da? -> FBM & Westpack B2B starten!',
                'start_date' => Carbon::create(2026, 11, 27, 10, 0, 0),
                'end_date' => Carbon::create(2026, 11, 27, 11, 0, 0),
                'is_all_day' => false,
                'reminder_minutes' => 60,
                'category' => 'project',
                'description' => "3 Wochen nach ELSTER-Antrag: Prüfen, ob der Brief vom Bundeszentralamt für Steuern (BZSt, 66738 Saarlouis) mit der Umsatzsteuer-Identifikationsnummer (DE...) vorliegt.\n\nSobald Steuernummer & USt-IdNr. vorliegen, direkt abarbeiten:\n1. Beide Nummern in den Seelenfunke-Stammdaten (SystemSettingSeeder / Admin-Einstellungen) hinterlegen.\n2. FBM (Friedrich Binder GmbH & Co. KG): KYC-Bogen (FB-ALL-06) + Gewerbeanmeldung per E-Mail an info@fbm.de senden.\n3. Westpack (Dänemark): Geschenkpapier Nr. 0751 (30 cm x 100 m, Art.-Nr. 03307510300, 67,90 € netto) steuerfrei per Reverse-Charge bestellen.\n4. Westpack Order #29000069521: Per E-Mail an sales@westpack.com nachträgliche Rechnungskorrektur auf Reverse-Charge und Erstattung der 29,19 € dänischen MwSt. anfragen.",
                'priority' => 'high',
            ],
            [
                'title' => 'Frist: Umsatzsteuer-Voranmeldung (UStVA) Nov 2026 (~850 € Vorsteuer erstatten lassen)',
                'start_date' => Carbon::create(2026, 12, 10, 9, 0, 0),
                'end_date' => Carbon::create(2026, 12, 10, 10, 0, 0),
                'is_all_day' => false,
                'reminder_minutes' => 120,
                'category' => 'project',
                'description' => "Abgabefrist für die erste Umsatzsteuer-Voranmeldung (November 2026) in ELSTER unter der neuen betrieblichen Steuernummer.\n- Alle Vorsteuerbeträge aus den Gründungs- und Equipment-Käufen (insb. xTool F2 Ultra UV Laser mit 783,55 € Vorsteuer, Laserschutz-Schulung mit 113,81 € Vorsteuer, Kartonagen, Rohlinge etc.) eintragen.\n- Erstattung des Vorsteuer-Überschusses erfolgt durch das Finanzamt Gifhorn direkt auf das hinterlegte FYRST-Geschäftskonto (DE76 2507 0370 0079 8629 00).",
                'priority' => 'high',
            ],
        ];

        foreach ($events as $eventData) {
            ManagementCalendarEvent::create(array_merge($eventData, [
                'send_email' => false,
            ]));
        }
    }
}
