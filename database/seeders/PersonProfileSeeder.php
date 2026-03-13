<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Funki\PersonProfile;
use Carbon\Carbon;

class PersonProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $profiles = [
            [
                'first_name' => 'Tim',
                'last_name' => 'Steinhauer',
                'nickname' => 'Tim, Bruder',
                'relation_type' => 'Bruder',
                'birthday' => '1990-07-13',
                'email' => 'erroryx@gmail.com',
                'phone' => '+49 176 45884064',
                'system_instructions' => <<<EOT
WICHTIGE FAKTEN ZU TIM STEINHAUER (Bruder):
- Wohnort: Meinersen, Deutschland
- Website: timsteinhauer.de
- Beruf: Senior Webentwickler PHP (Freiberuflich sowie angestellt bei bow Ingenieure GmbH)
- Fokus: Entwirft und programmiert seit 2012 individuelle Business Software, Webseiten und Online Shops für KMUs.
- Gemeinsamkeiten mit Alina: 20 gemeinsame Kontakte, gemeinsame berufliche Station bei High Office GmbH (Braunschweig).
- Fachkenntnisse: Laravel, Livewire, Kubernetes, Scrum, Vue.js, Elasticsearch, KeyCloak, RabbitMQ, Docker, Azure, Shopware, PHP, Node.js, MySQL uvm.
- Sprachen: Deutsch (Muttersprache), Englisch (Fließend)
- Zertifikate: Shopware certified developer & Partner
- Hobbys & Interessen: Musik, Serien, Gartenarbeiten, Renovieren, E-Mobilität, Umweltschutz, Lebenslanges Lernen.

KARRIERE (Zusammenfassung):
- Aktuell: bow Ingenieure GmbH (seit Feb. 2023)
- Aktuell: Freiberuflich (seit Juli 2019)
- Aktuell: Drohnify (Gesellschafter, seit Aug. 2018)
- Zuvor: High Office GmbH (Bereichsleiter Webentwicklung, 2019-2023)
- Zuvor: Löwenstark Digital Solutions / Online Marketing GmbH (verschiedene Führungspositionen Webentwicklung, 2012-2019)

STUDIUM:
- TEUTLOFF Technische Akademie (Informatik, 2010-2012)
EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Jan',
                'last_name' => 'Steinhauer',
                'nickname' => 'Papa',
                'relation_type' => 'Vater',
                'birthday' => '1963-10-25',
                'email' => 'jansteinhauer@t-online.de',
                'phone' => '0176 57793016 / +49 160 90592752',
                'system_instructions' => <<<EOT
WICHTIGE FAKTEN ZU JAN STEINHAUER (Papa):
- Wohnort: Gifhorn, Deutschland
- Beruf: Immobilienmakler bei der Volksbank (seit Januar 2008)
EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Kerstin',
                'last_name' => 'Steinhauer',
                'nickname' => 'Mum / Mutter',
                'relation_type' => 'Mutter',
                'birthday' => '1960-07-08',
                'email' => 'kerstinsteinhauer@freenet.de',
                'phone' => '01515 6336004',
                'system_instructions' => <<<EOT
WICHTIGE FAKTEN ZU KERSTIN STEINHAUER (Mutter):
- Kontakt: Alternativ kann sie auch über jansteinhauer@t-online.de erreicht werden.
EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Gloria',
                'last_name' => 'Rolinska',
                'nickname' => 'Prinzessin ❤️❤️❤️ oder Glorchik 💋💕',
                'relation_type' => 'Mitbewohnerin / Prinzessin',
                'birthday' => '2005-02-09',
                'email' => null,
                'phone' => '+49 160 98327512',
                'system_instructions' => <<<EOT
WICHTIGE FAKTEN ZU GLORCHIK ROLINSKA:
- Rolle: Alina's Mitbewohnerin, Prinzessin ❤️❤️❤️
EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Philip',
                'last_name' => 'Goik',
                'nickname' => 'Liebster Mensch',
                'relation_type' => 'Guter Freund',
                'birthday' => '2000-07-01',
                'email' => null,
                'phone' => '+49 176 52784439',
                'system_instructions' => <<<EOT
WICHTIGE FAKTEN ZU PHILIP GOIK:
- Rolle: Liebster Mensch ❤️ und es macht sehr viel Spaß mit ihm Zeit zu verbringen.
EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Alexander',
                'last_name' => 'Grüssmer',
                'nickname' => 'Alex',
                'relation_type' => 'Aller bester Freund',
                'birthday' => '1987-11-04',
                'email' => 'alexander@gruessmer.de',
                'phone' => '+49 173 4257940',
                'system_instructions' => <<<EOT
WICHTIGE FAKTEN ZU ALEXANDER GRÜSSMER (Alex):
- Wohnort: Braunschweig, Deutschland
- Rolle: Aller bester Freund von Alina. 23 gemeinsame Kontakte und eine gemeinsame berufliche Station bei High Office GmbH (Braunschweig).
- Beruf: Inhaber & Geschäftsführender Gesellschafter bei Felix Machts. eGbR (Braunschweig, seit Sep. 2021). Parallel auch Inhaber bei High Office GmbH (seit Apr. 2018).
- Fachkenntnisse: Consulting, Projektmanagement, IT-Consulting, Fachinformatiker (IHK), Wirtschaftswissenschaft (FH Südwestfalen).
- Sprachen: Deutsch (Muttersprache), Englisch (Gut), Spanisch (Grundlagen)
- Zertifikate: Certified ScrumMaster, Microsoft SharePoint Solutions
- Hobbys & Interessen: Kraftsport, Kraftdreikampf, CrossFit, Fitnesstraining.

KARRIERE (Zusammenfassung):
- Aktuell: Felix Machts. eGbR (Inhaber, seit 2021)
- Aktuell: High Office GmbH (Inhaber / GF, seit 2018)
- Zuvor: Jobline Personaldienstleistung GmbH (GF, 2023-2024)
- Zuvor: Embrace SBS GmbH (GF, 2017-2019)
- Zuvor: fme AG (Consultant, 2015-2017)
EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der LinkedIn Profil-Daten."
            ],
            [
                'first_name' => 'Lea',
                'last_name' => 'Hammermeister',
                'nickname' => 'Lea',
                'relation_type' => 'Bekannte/Freundin',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 155 61310667',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Lena',
                'last_name' => '',
                'nickname' => 'Lena',
                'relation_type' => 'Bekannte/Freundin',
                'birthday' => null,
                'email' => 'lena.hammer63@gmx.de',
                'phone' => '+49 176 87912406',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Lisa',
                'last_name' => 'Siemers',
                'nickname' => 'Lisa',
                'relation_type' => 'Bekannte/Freundin',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 162 1605435',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Maik',
                'last_name' => 'Steinhauer',
                'nickname' => 'Maik',
                'relation_type' => 'Familie (Steinhauer)',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 531 692760',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Mareike',
                'last_name' => 'Schneider',
                'nickname' => 'Mareike',
                'relation_type' => 'Medizinischer Kontakt',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 152 24354430',
                'system_instructions' => 'WICHTIGE FAKTEN: Sekretariat beim MVZ Gifhorn.',
            ],
            [
                'first_name' => 'Marius',
                'last_name' => 'Bähre',
                'nickname' => 'Marius',
                'relation_type' => 'Bekannter/Freund',
                'birthday' => null,
                'email' => 'baehre_marius@web.de',
                'phone' => '+49 151 54876705',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Marius',
                'last_name' => 'Reichelt',
                'nickname' => 'Marius',
                'relation_type' => 'Bekannter/Freund',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 157 88348729',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Marvin',
                'last_name' => 'Steinhauer',
                'nickname' => 'Marvin',
                'relation_type' => 'Familie (Steinhauer)',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 160 94769362',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Meral',
                'last_name' => 'Erkan-yildirim',
                'nickname' => 'Meral',
                'relation_type' => 'Bekannte/Freundin',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 178 5131548',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Mohammad',
                'last_name' => '',
                'nickname' => 'Mohammad',
                'relation_type' => 'Bekannter/Freund',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 176 86969290',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Pascal',
                'last_name' => 'Bär',
                'nickname' => 'Pascal',
                'relation_type' => 'Beruflicher / Bekannter Kontakt',
                'birthday' => null,
                'email' => 'pascaalbaer@icloud.com',
                'phone' => '+49 152 58769154',
                'system_instructions' => 'WICHTIGE FAKTEN: Arbeitet als Wachmann beim DRK in Uelzen.',
            ],
            [
                'first_name' => 'Frauenarztpraxis',
                'last_name' => 'BS',
                'nickname' => 'Frauenarzt / Praxis',
                'relation_type' => 'Arztpraxis',
                'birthday' => null,
                'email' => 'post@frauenarztpraxis-bs.de',
                'phone' => null,
                'system_instructions' => 'WICHTIGE FAKTEN: Frauenarztpraxis aus Braunschweig.',
            ],
            [
                'first_name' => 'Sara',
                'last_name' => 'Eggeling',
                'nickname' => 'Sara',
                'relation_type' => 'Bekannte/Freundin',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 157 86252685',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Sebastian',
                'last_name' => 'Funk',
                'nickname' => 'Sebastian (Boxenstopp Service)',
                'relation_type' => 'Beruflicher Kontakt / Dienstleister',
                'birthday' => null,
                'email' => 'info@boxenstopp-gifhorn.de',
                'phone' => '+49 175 4820295',
                'system_instructions' => 'WICHTIGE FAKTEN: Kundenbetreuer und Service beim BOXENSTOPP bei Funk & Lenz GbR (Gifhorn).',
            ],
            [
                'first_name' => 'Sebastian',
                'last_name' => 'Funk (Werkstatt)',
                'nickname' => 'Sebastian (Boxenstopp Werkstatt)',
                'relation_type' => 'Beruflicher Kontakt / Dienstleister',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 177 8365902',
                'system_instructions' => 'WICHTIGE FAKTEN: Gehört zur Boxenstopp Werkstatt.',
            ],
            [
                'first_name' => 'Sergej',
                'last_name' => 'Wolochow',
                'nickname' => 'Sergej',
                'relation_type' => 'Bekannter/Freund',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 176 17926330',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Theresa',
                'last_name' => 'Ollmann',
                'nickname' => 'Theresafilmiii',
                'relation_type' => 'Dienstleisterin',
                'birthday' => null,
                'email' => 'Theresa.Ollmann@icloud.com',
                'phone' => '+49 152 28719388',
                'system_instructions' => 'WICHTIGE FAKTEN: Kosmetikerin bei Black Magic Nails und Kosmetics.',
            ],
            [
                'first_name' => 'Timo',
                'last_name' => 'Schleicher',
                'nickname' => 'Timo',
                'relation_type' => 'Beruflicher Kontakt / Dienstleister',
                'birthday' => null,
                'email' => 'timo.schleicher92@web.de',
                'phone' => '+49 157 71889191',
                'system_instructions' => 'WICHTIGE FAKTEN: Elektrotechniker bei "Timo Schleicher und Kevin Riese GbR".',
            ],
            [
                'first_name' => 'William',
                'last_name' => 'Abdi',
                'nickname' => 'William',
                'relation_type' => 'Beruflicher Kontakt / Dienstleister',
                'birthday' => null,
                'email' => 'info@ihrkundendienst-gifhorn.de',
                'phone' => '+49 162 2306219',
                'system_instructions' => 'WICHTIGE FAKTEN: Geschäftsführer der Gehrmann & Abdi GmbH & Co. KG.',
            ]
        ];

        foreach ($profiles as $profileData) {
            $data = $profileData;
            // Parse birthday if exists
            if (isset($data['birthday'])) {
                $data['birthday'] = Carbon::parse($data['birthday']);
            }

            PersonProfile::updateOrCreate(
                ['first_name' => $data['first_name'], 'last_name' => $data['last_name']],
                $data
            );
        }
    }
}
