<?php

use App\Models\Ai\AiAgent;
use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Models\Ai\AiWorkspaceDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_knowledge_bases') || !Schema::hasTable('ai_knowledge_base_categories')) {
            return;
        }

        $category = AiKnowledgeBaseCategory::firstOrCreate(
            ['slug' => 'gesundheit-sozialrecht-bkk-firmus'],
            ['id' => (string) Str::uuid(), 'name' => 'Gesundheit & Sozialrecht (BKK firmus)']
        );

        $tagNames = [
            'BKK firmus',
            'Krankengeld',
            'eAU',
            'Krankschreibungen',
            'Aussteuerung',
            'Endokrinologikum',
            'Laborbefunde',
            'Humangenetik',
            'Hormontherapie',
            'Dorothea Jung',
            'Dr. Christian Schmidt',
            'Sozialgericht',
        ];

        $tagIds = [];
        if (Schema::hasTable('ai_knowledge_base_tags')) {
            foreach ($tagNames as $name) {
                $tag = AiKnowledgeBaseTag::firstOrCreate(
                    ['slug' => Str::slug($name)],
                    ['id' => (string) Str::uuid(), 'name' => $name]
                );
                $tagIds[$name] = $tag->id;
            }
        }

        // 1. MASTER-DOSSIER: KRANKSCHREIBUNGEN (AU / eAU 2016-2026) & AUSSTEUERUNG
        $auContent = <<<'MARKDOWN'
# Vollständiges Verzeichnis aller Krankschreibungen (AU / eAU 2016–2026) & Krankengeld-Aussteuerungsberechnung

**Versicherte:** Alina Steinhauer (geb. 01.09.1993, ehem. rechtl. Nick Steinhauer), Carl-Goerdeler-Ring 26, 38518 Gifhorn  
**Krankenkasse:** BKK firmus (Kostenträgerkennung / IK: `103121137`, Versicherten-Nr.: `O603571189`, Status: `1 00 00 00`)  
**Behandelnde Hausarztpraxis:** Gemeinschaftspraxis Wilhelm-Busch-Straße 2, 38542 Leiferde (Tel.: `05373-1814`, Fax: `05373-330635`, Betriebsstätten-Nr. / BSNR: `078354200`)  
- **Ärztin:** Dorothea Jung, Fachärztin für Allgemeinmedizin (Arzt-Nr. / LANR: `840902301`)  
- **Arzt:** Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin / Flugmedizin (Arzt-Nr. / LANR: `742315201`)  
**Archivort der Originalbelege (Desktop):** `C:\Users\konta\Desktop\Funkira\Dateien\Verarbeitet\Gesundheit & Medizin\Krankschreibungen`  

---

## 1. Lückenlose Aufstellung der AU-Bescheinigungen 2024–2026 (Belege 01 bis 15)

| Nr. | Zeitraum der AU (von – bis) | Festgestellt am | Art / Krankengeldfall | Ausstellender Arzt (LANR) | ICD-10-Diagnosecodes (gesichert) | Prüfnummer (PRF.NR.) & Originaldatei |
|:---|:---|:---|:---|:---|:---|:---|
| **01** | **29.04.2024 – 03.05.2024** | 30.04.2024 | Erstbescheinigung (arbeitsunfähig seit 29.04.2024) | Dr. med. Christian Schmidt (`742315201`) | `J06.9 G` (Akute Infektion der oberen Atemwege) | `Y/80/2108/36/266` (`45da3f4c-5832-4b54-9e64-61269bf584bf`) – `01_AU_2024-04-29_bis_2024-05-03.pdf` |
| **02** | **18.06.2024 – 08.07.2024** | 18.06.2024 | Erstbescheinigung (arbeitsunfähig seit 18.06.2024, Ausfertigung Arbeitgeber) | Dorothea Jung (`840902301`) | Übermittelt an Krankenkasse (Beginn der durchgehenden Haupt-AU) | `Y/80/2108/36/266` – `02_AU_2024-06-18_bis_2024-07-08.pdf` |
| **03** | **12.07.2024 – 02.08.2024** | 12.07.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` (Reaktion auf schwere Belastung) | `Y/80/2108/36/266` – `03_AU_2024-07-12_bis_2024-08-02.pdf` |
| **04** | **06.08.2024 – 02.09.2024** | 06.08.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` | `Y/80/2408/24/266` (`d9a73048-b871-45d3-9a0a-eb9b0451566d`) – `04_AU_2024-08-06_bis_2024-09-02.jpg` |
| **05** | **02.09.2024 – 30.09.2024** | 02.09.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` | `Y/80/2408/24/266` – `05_AU_2024-09-02_bis_2024-09-30.jpg` |
| **06** | **01.10.2024 – 31.10.2024** | 01.10.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G` | `Y/80/2408/24/266` (`c90ee109-3254-43a2-b374-156bc6147785`) – `06_AU_2024-10-01_bis_2024-10-31.jpg` |
| **07** | **22.10.2024 – 15.11.2024** | 22.10.2024 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G` (Neurasthenie / Erschöpfungssyndrom), `F64.0 G` (Transsexualismus) | `Y/80/2408/24/266` (`6f2d523e-3b9c-485c-b932-c24088ec8347`) – `07_AU_2024-10-22_bis_2024-11-15.jpg` |
| **08** | **28.01.2025 – 21.02.2025** | 28.01.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall; ab hier Vorname „Alina“) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`047b13f3-ee2a-4944-93c8-572eb4ccec49`) – `08_AU_2025-01-28_bis_2025-02-21.jpg` |
| **09** | **24.02.2025 – 21.03.2025** | 24.02.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`d545475f-1a5e-44a6-8cf3-32f6a1ca47e6`) – `09_AU_2025-02-24_bis_2025-03-21.pdf` |
| **10** | **21.03.2025 – 18.04.2025** | 21.03.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`67004ded-8abe-42cb-80ef-e449775beef4`) – `10_AU_2025-03-21_bis_2025-04-18.jpg` |
| **11** | **17.04.2025 – 28.04.2025** | 17.04.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dr. med. Christian Schmidt (`742315201`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`d68061f4-d2af-4ceb-8e6b-a8b65b598508`) – `11_AU_2025-04-17_bis_2025-04-28.jpg` |
| **12** | **28.04.2025 – 23.05.2025** | 28.04.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall; Hinweis: Dateiname lautete `bis_2025-04-28`, auf der Urkunde ist jedoch **bis einschließlich 23.05.2025** bescheinigt!) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`b268e8bc-627c-4cf8-b4e9-b537d8c41109`) – `12_AU_2025-04-28_bis_2025-04-28.jpg` |
| **13** | **22.05.2025 – 20.06.2025** | 22.05.2025 | Folgebescheinigung / ab 7. AU-Woche (Ausfertigung Krankenkasse) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`a1650880-f5e7-4f9e-8c57-f14968d44281`) – `13_AU_2025-05-22_bis_2025-20-06.jpg` |
| **14** | **29.12.2025 – 26.01.2026** | 29.12.2025 | Folgebescheinigung / ab 7. AU-Woche (Krankengeldfall) | Dorothea Jung (`840902301`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`691a400c-ddd2-478f-bc74-6b1bea3cba89`) – `14_AU_2025-12-29_bis_2026-01-26_Praxis_Jung.jpg` |
| **15** | **23.01.2026 – 29.01.2026** | 23.01.2026 | Folgebescheinigung bis zum **Aussteuerungstag** / ab 7. AU-Woche | Dr. med. Christian Schmidt (`742315201`) | `F43.9 G`, `F48.0 G`, `F64.0 G` | `Y/80/2408/24/266` (`70970c3c-5c10-4643-9584-bfc4157f471b`) – `15_AU_2026-01-23_bis_2026-01-29_Praxis_Dr_Schmidt_Aussteuerung.jpg` |

---

## 2. OP- und Post-OP-Zeitraum 2026 (Elektronische Krankschreibungen / eAU & BA-Meldungen)

Gemäß § 295 Abs. 1 Satz 1 SGB V wurden alle Arbeitsunfähigkeitsbescheinigungen im Jahr 2026 nach der geschlechtsangleichenden Operation (GA-OP vom 23.06.2026) direkt digital über die Telematikinfrastruktur als **eAU** an die BKK firmus übermittelt:

### A. Chronologie der Post-OP-Zeiträume 2026
1. **22.06.2026 – 12.07.2026** (Festgestellt 22.06.2026): Stationäre Aufnahme & GA-OP in den **Dr. Lubos Kliniken Bogenhausen** (München) – belegt durch Kostenübernahme & Liegebescheinigung der Klinik.
2. **12.07.2026 – 19.07.2026** (Festgestellt 12.07.2026): Post-stationäre Krankschreibung Dr. Lubos Kliniken – elektronisch gemeldet an BA am 13.07.2026.
3. **17.07.2026 – 07.08.2026** (Feststellungstag 17.07.2026): Nahtlose eAU Hausarztpraxis Leiferde (Dorothea Jung) – Eingang & Verarbeitung bei BKK firmus bestätigt am 13.07. / 21.07.2026.
4. **07.08.2026 – 03.09.2026** (Feststellungstag 07.08.2026): Nahtlose Folge-eAU Hausarztpraxis Leiferde – Eingang & Verarbeitung bei BKK firmus bestätigt am 03.08. / 10.08.2026.
5. **ab 04.09.2026 fortlaufend** (Feststellungstag 03.09.2026): Nahtlose Folge-eAU Hausarztpraxis Leiferde – Eingang & Verarbeitung bei BKK firmus bestätigt am 04.09.2026.

### B. Verbindliche Systembestätigungen aus der BKK-Service-App (Beleg 16)
- **13.07.2026, 10:15 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 17.07.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **21.07.2026, 07:31 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 17.07.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **23.07.2026, 07:32 Uhr (BKK firmus Nachricht):** *„Guten Tag, vielen Dank für die Einreichung Ihrer Unterlagen, wir werden diese schnellstmöglich prüfen und Ihnen eine Rückmeldung geben. Freundliche Grüße, Ihre BKK firmus“*
- **03.08.2026, 13:20 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 07.08.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **10.08.2026, 07:40 Uhr:** *„Es wurde eine neue Arbeitsunfähigkeitsbescheinigung mit dem Feststellungstag 03.09.2026 verarbeitet. Bitte schauen Sie in Meine Krankmeldungen.“*
- **04.09.2026, 07:23 Uhr:** Verarbeitung der nahtlosen Folgebescheinigung für September / Oktober 2026.

### C. Nachweise im Onlineportal der Bundesagentur für Arbeit (BA Gifhorn)
- **13.07.2026, 10:38 Uhr:** Veränderungsmitteilung versendet: Krankmeldung für den Zeitraum **22.06.2026 bis voraussichtlich einschließlich 19.07.2026**.
- **10.08.2026, 09:10 Uhr:** Veränderungsmitteilung versendet: Weiterhin krank für den Zeitraum **10.08.2026 bis voraussichtlich einschließlich 04.09.2026**.

---

## 3. Berechnung der Krankengeld-Bezugsdauer & Aussteuerung (Beleg 17)
- **Gesetzliche Höchstbezugsdauer (§ 48 Abs. 1 SGB V):** **546 Kalendertage (78 Wochen)** innerhalb einer Blockfrist von 3 Jahren wegen derselben Krankheit.
- **Rechnerische Kalkulation (Stand 13.07.2025):**
  - Referenzbeginn: 23.08.2024 (+ 546 Tage = theoretisches Ende 21.02.2026; bis 13.07.2025 waren 324 Tage verbraucht, Rest 222 Tage).
  - Tatsächliche Aussteuerung durch die BKK firmus unter Einrechnung der Vorerkrankungszeiten ab 18.06.2024: **23.01.2026** (bzw. Folge-AU Nr. 15 bis **29.01.2026**, Übergang in ALG 1 am 30.01.2026, woraus die 6-tägige Nahtlosigkeitslücke vom 24.01.–29.01.2026 über 361,26 € resultierte).

---

## 4. Historische Arbeitsunfähigkeitsbescheinigungen (2016–2022)
Ebenfalls im Desktop-Archiv (`Krankschreibungen/Historische_AUs_2016-2022/`) hinterlegt:
- `AU_2016-11-22_bis_2020-01-30_Sammelakte.pdf` (Sammeldokumentation der Jahre 2016–2020)
- `AU_2020-07-14_bis_2020-07-17_HEK.pdf` (14.07.2020 – 17.07.2020, Hanseatische Krankenkasse HEK)
- `AU_2020-08-18_bis_2020-08-21.pdf` (18.08.2020 – 21.08.2020)
- `AU_2021-07-23_bis_2021-07-28.pdf` (23.07.2021 – 28.07.2021)
- `AU_2021-12-02_bis_2021-12-03.pdf` (02.12.2021 – 03.12.2021)
- `AU_2022-12-19_bis_2022-12-21.jpg` (19.12.2022 – 21.12.2022)
MARKDOWN;

        $auArticle = AiKnowledgeBase::updateOrCreate(
            ['slug' => 'krankschreibungen-au-eau-chronologie-aussteuerung-2016-2026'],
            [
                'id' => (string) Str::uuid(),
                'title' => 'Krankschreibungen (AU / eAU 2016–2026), ICD-10-Diagnosen & Aussteuerungsberechnung',
                'content' => $auContent,
                'is_published' => true,
                'ai_knowledge_base_category_id' => $category->id,
            ]
        );

        if (!empty($tagIds)) {
            $auArticle->tags()->sync(array_values(array_intersect_key($tagIds, array_flip([
                'BKK firmus', 'Krankengeld', 'eAU', 'Krankschreibungen', 'Aussteuerung', 'Dorothea Jung', 'Dr. Christian Schmidt', 'Sozialgericht'
            ]))));
        }

        // 2. MASTER-DOSSIER: ENDOKRINOLOGIKUM HANNOVER – ARZTBRIEF, HUMANGENETIK & LABORBEFUNDE (2024)
        $endoContent = <<<'MARKDOWN'
# Endokrinologikum Hannover: Arztbrief, Humangenetik (46,XY / FISH) & Vollständige Laborbefunde (April–Juni 2024)

**Patientin:** Frau Alina (rechtl. damals Nick) Steinhauer, geb. 01.09.1993, wohnhaft Carl-Goerdeler-Ring 26, 38518 Gifhorn  
**Fachärztliches Zentrum:** MVZ Endokrinologikum Hannover (Zentrum für Hormon- und Stoffwechselerkrankungen, Rundestraße 10, 30161 Hannover, Tel. `0511 21 55 58 - 2000`, Fax `0511 21 55 58 - 2119`)  
**Behandelnde Ärztin:** **Dr. med. Susanne Krusche**, Fachärztin für Innere Medizin, Endokrinologie und Diabetologie (LANR: `356126803`, Diktatzeichen: `SK/wei`)  
**Empfänger des Arztbriefs vom 05.06.2024:** Herrn Dr. med. Christian Schmidt, Facharzt für Allgemeinmedizin / Flugmedizin, Wilhelm-Busch-Straße 2, 38542 Leiferde (nachrichtlich an Frau Alina Steinhauer)  
**Labor:** amedes MVZ wagnerstibbe für Laboratoriumsmedizin, Hämostaseologie, Humangenetik und Mikrobiologie Hannover, Georgstraße 50, 30159 Hannover  
**Archivort des Originaldokuments (9 Seiten PDF, Desktop):** `C:\Users\konta\Desktop\Funkira\Dateien\Verarbeitet\Gesundheit & Medizin\Atteste\Endokrinologikum_Laborbefunde.pdf`  

---

## 1. Ärztlicher Befundbericht & Epikrise (Vorstellung am 09.04.2024, Brief vom 05.06.2024)

### Diagnosen
1. **Transidentität Mann-zu-Frau (MtF)**
2. **Unauffälliger männlicher Chromosomensatz (`46,XY`)**
3. **Vorliegendes Indikationsschreiben**

### Anamnese & Körperlicher Untersuchungsbefund (09.04.2024)
- **Anamnese:** Vorstellung mit dem Wunsch, das Geschlecht in Richtung Frau zu ändern. Die Patientin berichtet, sich schon während des ganzen Lebens eher als Frau gefühlt zu haben. Sie habe diese Gefühle zunächst unterdrückt, ein Haus gebaut, eine Frau geheiratet und einen Sohn (zum Untersuchungszeitpunkt 1,5 Jahre alt).
- **Vormedikation:** Keine.
- **Körperliche Untersuchung (30-jährige Patientin):**
  - **Blutdruck:** `129/71 mmHg`
  - **Puls:** `67 S/min`
  - **Körpergröße:** `186 cm`
  - **Körpergewicht:** `81,7 kg`

### Epikrise (Dr. med. Susanne Krusche)
> *„Unsere Untersuchung zeigen einen unauffälligen männlichen Chromosomensatz, normale männliche Geschlechtshormone, keine auffällige Gerinnungsdiagnostik. Ein Indikationsschreiben liegt vor. Somit sind die Voraussetzungen für den Beginn einer gegengeschlechtlichen Hormontherapie gegeben, die Patientin stellt sich am 08.05.2024 zu einer Besprechung und schriftlichen Aufklärung über Nebenwirkungen und Risiken der Hormontherapie vor.“*

---

## 2. Klinische Chemie, Elektrolyte, Schilddrüse & Vitamine (Entnahme 09.04.2024, 11:42 Uhr)
*Barcodenummer: `7019021329` | Tagesnummer: `HQ YD09 8623` | Endbefund: 11.04.2024 (validiert von Dr. Arndt Gröning)*

| Parameter | Messwert | Einheit | Referenzbereich | Bewertung |
|:---|:---|:---|:---|:---|
| **Glucose** | `99` | mg/dl | `< 100` | Normal |
| **Hämoglobin A1c (HbA1c)** | `4,7` | % | `< 6,2` | Normal |
| **HbA1c (IFCC)** | `28` | mmol/mol | `< 44` | Normal |
| **Harnsäure** | `6,4` | mg/dl | `3,4 – 7,0` | Normal |
| **GPT (ALAT)** | `17` | U/l | `< 50` | Normal |
| **GOT (ASAT)** | `22` | U/l | `< 50` | Normal |
| **gamma-GT** | `8` | U/l | `< 60` | Normal |
| **Alk. Phosphatase** | `58` | U/l | `40 – 129` | Normal |
| **Creatinin (Jaffé)** | `1,1` | mg/dl | `< 1,2` | Normal |
| **GFR (CKD-EPI)** | `90` | ml/min/1,73qm | `> 60` | Normal |
| **Harnstoff** | `22` | mg/dl | `19 – 44` | Normal |
| **Natrium** | `141` | mmol/l | `136 – 145` | Normal |
| **Kalium (Serum)** | `4,0` | mmol/l | `3,6 – 5,4` (Plasma `3,4–5,1`) | Normal |
| **Calcium** | `2,4` | mmol/l | `2,15 – 2,50` | Normal |
| **Phosphat, anorg.** | `0,97` | mmol/l | `0,80 – 1,60` | Normal |
| **C-reaktives Protein (CRP)** | `0,2` | mg/dl | `< 0,5` | Normal (keine Entzündung) |
| **25-OH-Vitamin D** | `40` ↓ | nmol/l | Zielwert Prävention `> 75` | **Vitamin-D-Mangel** (`< 50 nmol/l`) |
| **FT3 (Freies Trijodthyronin)** | `3,4` | ng/l | `1,9 – 5,1` | Normal (euthyreot) |
| **FT4 (Freies Thyroxin)** | `1,3` | ng/dl | `0,9 – 1,7` | Normal (euthyreot) |
| **TSH, basal** | `1,90` | mIU/l | `0,4 – 4,0` | Normal (euthyreot) |

---

## 3. Endokrinologische Ausgangsdiagnostik vor HRT (Entnahme 09.04.2024, 11:42 Uhr)

| Hormon / Parameter | Messwert | Einheit | Referenzbereich | Bewertung |
|:---|:---|:---|:---|:---|
| **FSH, basal** | `2,9` | IU/l | `1,5 – 12,4` | Normal |
| **LH, basal** | `3,0` | IU/l | `1,7 – 8,6` | Normal |
| **17-beta-Östradiol (E2)** | `39` | ng/l | `11,3 – 43,2` | Normal (Ausgangswert) |
| **Prolaktin, 1. Basalwert** | `186` / `8,7` | mIU/l / ng/ml | `86 – 324` / `4,0 – 15,2` | Normal |
| **Testosteron, gesamt** | `6,04` | µg/l | `2,49 – 8,36` | Normal (Ausgangswert vor HRT) |
| **Freier Androgenindex (FAI)** | `54` | – | `36 – 155` | Normal |
| **SHBG** | `39` | nmol/l | `18 – 54` | Normal |
| **Androstendion (ECLIA)** | `0,94` | ng/ml | `0,34 – 2,13` | Normal |
| **17-OH-Progesteron** | `1,2` | µg/l | `0,6 – 2,2` | Normal |
| **ACTH** | `19,2` | ng/l | `7,2 – 63,3` | Normal |
| **Cortisol** | `147` | µg/l | vormittags `48 – 195` | Normal |

---

## 4. Hämatologie / Kleines Blutbild im Verlauf (09.04.2024 vs. 08.05.2024)
*Kontrollbefund vom 08.05.2024 (Entnahme 14:10 Uhr, Barcode `7019329412`, Tagesnr. `HQ YE08 9676`, validiert von Dr. Andre Siegel):*

| Parameter | 09.04.2024 (EDTA / Citrat) | 08.05.2024 (Kontrolle zum HRT-Start) | Einheit | Referenzbereich |
|:---|:---|:---|:---|:---|
| **Leukozyten** | `5,6` / `5,8` | **`7,6`** | G/l | `4,0 – 9,0` |
| **Erythrozyten** | `5,87` / `5,89` | **`5,36`** | T/l | `4,3 – 5,9` |
| **Hämoglobin** | `17,0` | **`15,5`** | g/dl | `14,0 – 18,0` |
| **Hämatokrit** | `48,2` / `50` | **`44,9`** | % | `40 – 50` |
| **MCV** | `82,1` / `84,9` | **`83,8`** | fl | `80 – 96` |
| **MCH** | `29,0` / `28,9` | **`28,9`** | pg | `28 – 32` |
| **MCHC** | `35,3` / `34,0` | **`34,5`** | g/dl | `30 – 36` |
| **Thrombozyten** | `144` ↓ / `139` ↓ | **`203` (normalisiert)** | G/l | `150 – 400` |

---

## 5. Fettstoffwechsel, Gerinnungsdiagnostik & Thrombophilie-Ausschluss (09.04.2024)
*Barcodenummer: `7019021355` | Tagesnummer: `HQ YD09 8609` | Endbefund: 12.04.2024 (validiert von Dr. Frauke Bergmann)*

- **Fettstoffwechsel / Atheroskleroserisiko:**
  - **Lp(a) (Turbidimetrie, Roche):** `< 19 nmol/l` (Ref: `< 75 nmol/l`) – unauffällig
  - **Homocystein:** `12,1 µmol/l` (Ref: `< 15,0 µmol/l`) – unauffällig
- **Gerinnungsparameter:**
  - **Quick-Test:** `70 %` ↓ (Ref: `80 – 126 %`), **INR:** `1,2`
  - **Part. Thromboplastinzeit (PTT):** `34 sec.` (Ref: `23 – 34 sec.`)
  - **D-Dimere:** `170 µg/l` (Ref: `< 500 µg/l`)
  - **Einzelfaktoren:** Faktor VII `60 %` (`50–130 %`), Faktor VIII `55 %` (`50–150 %`), Faktor IX `90 %` (`65–150 %`), Antithrombin III `101 %` (`80–120 %`), Protein C `86 %` (`70–140 %`), Protein S frei `97 %` (`64–139 %`), aPC-Resistenz `2,73 Ratio` (normal `> 2,18`).
- **Molekulargenetische & Immunologische Thrombophilie-Diagnostik:**
  - **Faktor-V-Leiden-Genotyp (`p.Arg506Gln`):** **nicht nachweisbar**
  - **Prothrombin-Genotyp (`20210G>A`):** **nicht nachweisbar**
  - **Lupusantikoagulanz (Lupus-Inhibitor):** dRVVT-Test `negativ`, LA-Screen `0,69` (`< 1,2`), PTT LA `negativ`, LA-Quotient `0,93` (`< 1,10 = negativ`).
  - **Autoantikörper (Antiphospholipid):** Cardiolipin IgG (FIA) `negativ`, Cardiolipin IgM (FIA) `negativ`, beta-2-Glykoprotein 1 IgG (FIA) `negativ`, beta-2-Glykoprotein 1 IgM (FIA) `negativ`.
- **Hämostaseologische Beurteilung (Dr. Frauke Bergmann):**
  - Eine Faktor-VII-Verminderung sowie eine Lebersynthesestörung konnten ausgeschlossen werden (Antithrombin normal). **Kein Hinweis auf eine angeborene oder erworbene Thrombophilie** (wichtig für die Sicherheit der Östrogen-Hormontherapie!).

---

## 6. Humangenetische & Molekularzytogenetische Diagnostik (09.04.2024 – 29.04.2024)
*Barcodenummer: `7019021362` | Tagesnummer: `HQ YD09 8607` | Endbefund: 29.04.2024 (validiert von Prof. Dr. med. Gudrun Göhring / MVZ Institut für Labormedizin und klinische Genetik Rhein/Ruhr)*

- **Indikation:** Transgender (Mann-zu-Frau)
- **1. Chromosomendiagnostik aus Lymphozyten (GTG-Färbung, 12 Metaphasen ausgewertet, ca. 400 Banden):**
  - **Karyotyp:** **`46,XY` (nach ISCN 2020)**
  - **Ergebnis:** Unauffälliger männlicher Chromosomensatz; keine Hinweise auf eine numerische oder strukturelle Chromosomenveränderung.
- **2. Molekularzytogenetische Diagnostik / FISH (Fluoreszenz-in-situ-Hybridisierung, Sonden `LSI SRY Spectrum Orange / CEP X Spectrum Green`, Fa. Abbott Molecular):**
  - **Karyotyp:** **`nuc ish(DXZ1,SRY)x1` (ISCN 2020)**
  - **Ergebnis:** In **197 ausgewerteten Interphasezellkernen** und **8 Metaphasen** fand sich jeweils ein Signal für die Zentromerregion des X-Chromosoms (`DXZ1`) und ein Signal für die `SRY`-Region des Y-Chromosoms (`Yp11.3`).
  - **Beurteilung:** Spricht gegen das Vorliegen einer Deletion der `SRY`-Region; zudem **kein Hinweis auf ein gonosomales Mosaik**.
MARKDOWN;

        $endoArticle = AiKnowledgeBase::updateOrCreate(
            ['slug' => 'endokrinologikum-hannover-laborbefunde-humangenetik-hormonstatus-2024'],
            [
                'id' => (string) Str::uuid(),
                'title' => 'Endokrinologikum Hannover: Arztbrief, Humangenetik (46,XY / FISH) & Laborbefunde (2024)',
                'content' => $endoContent,
                'is_published' => true,
                'ai_knowledge_base_category_id' => $category->id,
            ]
        );

        if (!empty($tagIds)) {
            $endoArticle->tags()->sync(array_values(array_intersect_key($tagIds, array_flip([
                'Endokrinologikum', 'Laborbefunde', 'Humangenetik', 'Hormontherapie', 'Dr. Christian Schmidt', 'BKK firmus'
            ]))));
        }

        // 3. Bereinige ai_workspace_documents von den ausgelagerten Pfaden und Duplikaten
        if (Schema::hasTable('ai_workspace_documents')) {
            AiWorkspaceDocument::where('file_path', 'like', '%Gesundheit/Krankschreibungen%')
                ->orWhere('file_path', 'like', '%Endokrinologikum_Laborbefunde.pdf%')
                ->orWhere('file_path', 'like', '%WiderspruchsauschussApril (1).pdf%')
                ->orWhere('file_path', 'like', '%Dokumente/Allgemein/Aerztliches_Attest_Frau_Jung_Alina_Steinhauer.pdf%')
                ->orWhere('file_path', 'like', '%Dokumente/Allgemein/9650226941682.pdf%')
                ->orWhere('file_path', 'like', '%Dokumente/Kalender/kalender_export_%')
                ->delete();
        }

        // 4. Schärfe Dr. Funki System-Prompt mit den neuen Detaildaten
        if (Schema::hasTable('ai_agents')) {
            $drFunki = AiAgent::where('name', 'Dr. Funki')->first();
            if ($drFunki && !str_contains((string) $drFunki->system_prompt, 'Endokrinologikum Hannover')) {
                $addition = "\n   - Endokrinologikum Hannover (Dr. med. Susanne Krusche, Brief 05.06.2024, Labor 09.04./08.05.2024): Karyotyp 46,XY & FISH nuc ish(DXZ1,SRY)x1 unauffällig, Thrombophilie ausgeschlossen (Faktor-V-Leiden & Prothrombin negativ, AT III 101%), HRT-Freigabe zum 08.05.2024.\n   - Krankschreibungen (AU 01–15 von 2024 bis Aussteuerung 29.01.2026 durch Dorothea Jung BSNR 078354200 / LANR 840902301 & Dr. Christian Schmidt LANR 742315201, Diagnosen F43.9 G, F48.0 G, F64.0 G) sowie lückenlose Post-OP eAU ab 22.06.2026 sind vollständig in der Wissensdatenbank (brain_search) hinterlegt.";
                $drFunki->update([
                    'system_prompt' => rtrim((string) $drFunki->system_prompt) . $addition,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_knowledge_bases')) {
            AiKnowledgeBase::whereIn('slug', [
                'krankschreibungen-au-eau-chronologie-aussteuerung-2016-2026',
                'endokrinologikum-hannover-laborbefunde-humangenetik-hormonstatus-2024',
            ])->delete();
        }
    }
};
