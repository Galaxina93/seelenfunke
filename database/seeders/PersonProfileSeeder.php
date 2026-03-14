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
                'avatar_path' => 'person_profiles/tim_steinhauer.jpg',
                'links' => [
                    ['name' => 'Website', 'url' => 'https://timsteinhauer.de'],
                    ['name' => 'GitHub', 'url' => 'https://github.com/tim-steinhauer']
                ],
                'birthday' => '1990-07-13',
                'email' => 'erroryx@gmail.com',
                'phone' => '+49 176 45884064',
                'street' => 'Musterstraße 1',
                'postal_code' => '38536',
                'city' => 'Meinersen',
                'country' => 'Deutschland',
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
                'relation_type' => 'Vater/Papa',
                'avatar_path' => 'person_profiles/jan_steinhauer.jpg',
                'links' => [],
                'birthday' => '1962-10-25',
                'email' => 'jansteinhauer@t-online.de',
                'phone' => '0176 57793016 / +49 160 90592752',
                'street' => 'Holzweg 11',
                'postal_code' => '38536',
                'city' => 'Meinersen',
                'country' => 'Deutschland',
                'system_instructions' => <<<EOT
                WICHTIGE FAKTEN ZU JAN STEINHAUER (Papa):
                - Vater von der Herrin Alina
                - Wohnort: 38536 Meinernsen, Holzweg 11 Deutschland
                - Ehemaliger Beruf: Immobilienmakler bei der Volksbank (seit Januar 2008)
                - Aktuell: In Rente, kümmert sich ehrenamtlich um den "F I M) der Gemeinde Meinersen
                - Arbeitsplatz: Schlechte Ausstattung, zu kleine Büro, zuviele Schränke
                Verbesserungsvorschlag: Räumt den Raum leer, schafft mehr Platz für einen guten Arbeitsplatz.
                EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Kerstin',
                'last_name' => 'Steinhauer',
                'nickname' => 'Mum / Mutter / Mama',
                'relation_type' => 'Mutter',
                'avatar_path' => 'person_profiles/kerstin_steinhauer.jpg',
                'links' => [],
                'birthday' => '1960-07-08',
                'email' => 'kerstinsteinhauer@freenet.de',
                'phone' => '01515 6336004',
                'street' => 'Holzweg 11',
                'postal_code' => '38536',
                'city' => 'Meinersen',
                'country' => 'Deutschland',
                'system_instructions' => <<<EOT
                WICHTIGE FAKTEN ZU KERSTIN STEINHAUER (Mutter):
                - Mutter von der Herrin Alina
                - Wohnort: 38536 Meinernsen, Holzweg 11 Deutschland
                - Ehemaliger Beruf: Apothekerin, hat damals in Meinersen in der Litzapotheke gearbeitet. Ihre Kollegin Albertie ist anscheinend ganz nett gewesen.
                - Kontakt: Alternativ kann sie auch über jansteinhauer@t-online.de erreicht werden.
                - Lässt sich gerne massieren
                - Schreibt sich immer noch altmodisch die Termine in ein Terminkalender
                - Befindet sich auch in Rente
                EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Gloria',
                'last_name' => 'Rolinska',
                'nickname' => 'Prinzessin ❤️❤️❤️ oder Glorchik 💋💕',
                'relation_type' => 'Mitbewohnerin / Prinzessin',
                'avatar_path' => 'person_profiles/gloria_rolinska.jpg',
                'birthday' => '2005-02-09',
                'email' => null,
                'phone' => '+49 160 98327512',
                'system_instructions' => <<<EOT
                WICHTIGE FAKTEN ZU GLORCHIK ROLINSKA:
                - Rolle: Alina's Mitbewohnerin, Prinzessin ❤️❤️❤️
                - Zukunft: Sie muss dringend den B2 Deutschkurs machen, aber leider scheitert es an der deutschen Bürokratie
                - Beruf: Sie möchte aktuell gerne Köchin werden
                EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Philip',
                'last_name' => 'Goik',
                'nickname' => 'Liebster Mensch',
                'relation_type' => 'Guter Freund mit Sternchen',
                'avatar_path' => 'person_profiles/philip_goik.jpg',
                'birthday' => '2000-07-01',
                'email' => null,
                'phone' => '+49 176 52784439',
                'system_instructions' => <<<EOT
                WICHTIGE FAKTEN ZU PHILIP GOIK:
                - Rolle: Ein sehr lieber Mensch ❤️
                - Beruf: Soldat
                - Freizeit: Verbringt unglaublich gerne Zeit mit der Herrin Alina. (Schwimmen, essen und vieles mehr)
                - Hobby: Erlebt gerne was mit seinen Freunden und denkt wirklich das Jonas mehr KI Wissen hat als Alina.
                EOT,
                'ai_learned_facts' => "\n[13.03.2026] Initiale Synchronisation der Profil-Daten."
            ],
            [
                'first_name' => 'Alexander',
                'last_name' => 'Grüssmer',
                'nickname' => 'Alex',
                'relation_type' => 'Aller bester Freund',
                'avatar_path' => 'person_profiles/alexander_gruessmer.jpg',
                'links' => [
                    ['name' => 'LinkedIn', 'url' => 'https://linkedin.com/in/alexgrussmer']
                ],
                'birthday' => '1987-11-04',
                'email' => 'alexander@gruessmer.de',
                'phone' => '+49 173 4257940',
                'street' => 'Musterweg 5',
                'postal_code' => '38100',
                'city' => 'Braunschweig',
                'country' => 'Deutschland',
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
                'first_name' => 'Lena',
                'last_name' => 'Salewski',
                'nickname' => 'Lena',
                'relation_type' => 'Freundin',
                'avatar_path' => 'person_profiles/lena_salewski.jpg',
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
                'avatar_path' => 'person_profiles/lisa_siemers.jpg',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 162 1605435',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Marius',
                'last_name' => 'Reichelt',
                'nickname' => 'Marius',
                'relation_type' => 'Bekannter/Freund',
                'avatar_path' => 'person_profiles/marius_reichelt.jpg',
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
                'avatar_path' => 'person_profiles/marvin_steinhauer.jpg',
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
                'avatar_path' => 'person_profiles/meral_erkan_yildirim.jpg',
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
                'avatar_path' => 'person_profiles/mohammad-mahdiyan.jpg',
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
                'avatar_path' => 'person_profiles/pascal_baer.jpg',
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
                'avatar_path' => 'person_profiles/judith_bollmann.jpg',
                'birthday' => null,
                'email' => 'post@frauenarztpraxis-bs.de',
                'phone' => "053173535",
                'system_instructions' => 'WICHTIGE FAKTEN: Frauenarztpraxis aus Braunschweig.',
            ],
            [
                'first_name' => 'Sara',
                'last_name' => 'Eggeling',
                'nickname' => 'Sara',
                'relation_type' => 'Bekannte/Freundin',
                'avatar_path' => 'person_profiles/sara_eggeling.jpg',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 157 86252685',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Sebastian',
                'last_name' => 'Funk (Werkstatt)',
                'nickname' => 'Sebastian (Boxenstopp Werkstatt)',
                'relation_type' => 'Beruflicher Kontakt / Dienstleister',
                'avatar_path' => 'person_profiles/sebastian_funk.jpg',
                'birthday' => null,
                'email' => null,
                'phone' => '05371936700',
                'system_instructions' => 'WICHTIGE FAKTEN: Gehört zur Boxenstopp Werkstatt.',
            ],
            [
                'first_name' => 'Sergei',
                'last_name' => 'Wolochow',
                'nickname' => 'Sergei',
                'relation_type' => 'Bekannter/Freund',
                'avatar_path' => 'person_profiles/sergei_wolochow.jpg',
                'birthday' => null,
                'email' => null,
                'phone' => '+49 176 17926330',
                'system_instructions' => 'Keine speziellen Instruktionen bisher.',
            ],
            [
                'first_name' => 'Theresa',
                'last_name' => 'Ollmann',
                'nickname' => 'Theresafilmiii',
                'relation_type' => 'Dienstleisterin / Sehr gute Freundin',
                'avatar_path' => 'person_profiles/theresa_ollmann.jpg',
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
                'avatar_path' => 'person_profiles/timo_schleicher.jpg',
                'birthday' => null,
                'email' => 'timo.schleicher92@web.de',
                'phone' => '+49 157 71889191',
                'system_instructions' => 'WICHTIGE FAKTEN: Elektrotechniker bei "Timo Schleicher und Kevin Riese GbR".',
            ],
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
