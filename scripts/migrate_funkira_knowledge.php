<?php

require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Ai\AiKnowledgeBase;
use App\Models\Ai\AiKnowledgeBaseCategory;
use App\Models\Ai\AiKnowledgeBaseTag;
use App\Models\Ai\AiAgent;
use App\Models\Management\ManagementTask;
use App\Models\Management\ManagementTaskList;
use Illuminate\Support\Str;

echo "=== STARTE MIGRATION DES FUNKIRA-WISSENS IN SEELENFUNKE ===\n";

// 1. KATEGORIEN ANLEGEN
$categories = [
    [
        'name' => 'Gesundheit & Sozialrecht (BKK firmus)',
        'slug' => 'gesundheit-sozialrecht-bkk-firmus',
    ],
    [
        'name' => 'Existenzgründung & Arbeitsagentur',
        'slug' => 'existenzgruendung-arbeitsagentur',
    ],
    [
        'name' => 'Finanz-Audit & Leistungsansprüche',
        'slug' => 'finanz-audit-leistungsansprueche',
    ]
];

$catMap = [];
foreach ($categories as $catData) {
    $cat = AiKnowledgeBaseCategory::firstOrCreate(
        ['slug' => $catData['slug']],
        ['id' => (string) Str::uuid(), 'name' => $catData['name']]
    );
    $catMap[$catData['slug']] = $cat->id;
    echo "✓ Kategorie bereit: {$cat->name}\n";
}

// 2. TAGS ANLEGEN
$tagNames = [
    'BKK firmus', 'Krankengeld', 'eAU', 'Widerspruch', 'GA-OP', 
    'Arbeitsagentur', 'ALG 1', 'Gründungszuschuss', 'Mein Seelenfunke', 
    'Tragfähigkeit', 'Fristen', 'Audit', 'Sozialgericht', 'BAS'
];
$tagMap = [];
foreach ($tagNames as $tName) {
    $slug = Str::slug($tName);
    $tag = AiKnowledgeBaseTag::firstOrCreate(
        ['slug' => $slug],
        ['id' => (string) Str::uuid(), 'name' => $tName]
    );
    $tagMap[$tName] = $tag->id;
}
echo "✓ " . count($tagMap) . " Tags bereit.\n";

// 3. MASTER-DOSSIER 1: 25-SCHRITTE CHRONOLOGIE BKK FIRMUS
$timelineContent = <<<'MARKDOWN'
# Master-Dossier: BKK firmus Verfahren & Vollständige 25-Schritte-Chronologie

**Mandantin**: Alina Steinhauer  
**Gegnerin**: BKK firmus (Körperschaft des öffentlichen Rechts)  
**Aktenzeichen / Kennungen**: KV-Nr: O603571189 | Steuer-ID: 66324780911 | Kundennummer BA: 241D129534  
**Stand**: 06.10.2026 | **Priorität**: Akut / Höchste Dringlichkeit  

---

## Chronologischer Verfahrensablauf (Schritte 01 bis 25)

### PHASE 1: OP-Antrag & Kassenverzögerung (2024–2025)
- **Schritt 01 (16.12.2024)**: Alina reicht formellen Antrag auf Kostenübernahme für die geschlechtsangleichende Großoperation (GA-OP) bei der BKK firmus ein (§ 27 Abs. 1 SGB V). Nachweis: `9650078742642.pdf`.
- **Schritt 02 (19.06.2025)**: Nach über 6 Monaten rechtswidriger Verschleppung leitet die BKK firmus die Akte erst an den Medizinischen Dienst (MD) weiter (§ 275 SGB V). Wertvolle Kliniktermine verfallen.
- **Schritt 03 (07.07.2025)**: BKK firmus erlässt rechtswidrigen Ablehnungsbescheid auf Basis eines mangelhaften MD-Gutachtens.
- **Schritt 04 (10.07.2025)**: Alina legt innerhalb von 3 Tagen fundierten und begründeten Widerspruch ein (§ 84 SGG).
- **Schritt 05 (15.07.2025)**: **Voller Erfolg**: BKK firmus hebt die Ablehnung auf und bewilligt die Kostenübernahme für die GA-Großoperation vollumfänglich. Nachweis: Abhilfebescheid `9650117180424.pdf`.
- **Schritt 06 (15.10.2025)**: Widerspruch gegen Verzögerung der Nadelepilation / Barthaarentfernung.

### PHASE 2: Aussteuerung, Nahtlosigkeit & ALG 1 (Januar – Juni 2026)
- **Schritt 07 (23.01.2026)**: Aussteuerung nach 78 Wochen Krankengeldbezug bei der BKK firmus (§ 48 Abs. 1 SGB V).
- **Schritt 08 (24.01.2026)**: **Entstehung der 6-Tage-Lücke**: BKK-Krankengeld endet am 23.01.2026, der Bewilligungsbescheid der Arbeitsagentur setzt erst am 30.01.2026 ein. Zeitraum 24.01. bis 29.01.2026 (6 Tage à 60,21 € = 361,26 €) ist rechtswidrig ungedeckt.
- **Schritt 09 (30.01.2026)**: Bewilligung von Arbeitslosengeld 1 (ALG 1) durch die Agentur für Arbeit Gifhorn. Tagessatz: **60,21 €** (monatlich 1.806,30 €). Bewilligungsbescheid vom 25.02.2026.

### PHASE 3: Neuer Krankheitsfall & Leistungsfortzahlung (Juni – August 2026)
- **Schritt 10 (22.06.2026)**: Eintritt einer neuen, eigenständigen Arbeitsunfähigkeit.
- **Schritt 11 (17.07.2026)**: Beginn der lückenlosen ärztlichen Arbeitsunfähigkeitsbescheinigungen (Erst-AU Hausarztpraxis Dorothea Jung).
- **Schritt 12 (02.08.2026)**: Ablauf der gesetzlichen 6 Wochen (42 Kalendertage) Leistungsfortzahlung durch die Arbeitsagentur gem. § 146 Abs. 1 SGB III. Letzte Teilzahlung am 25.08.2026 über 120,42 € (für 01.–02.08.2026).

### PHASE 4: Akuter Krankengeld-Konflikt mit BKK firmus (August – Oktober 2026)
- **Schritt 13 (03.08.2026)**: **Gesetzlicher Übergang in das Krankengeld (§ 44 Abs. 1, § 47b SGB V)**: Mit Tag 43 der Arbeitsunfähigkeit ist zwingend und unmittelbar kraft Gesetzes die BKK firmus leistungspflichtig. Tagessatz entspricht dem bisherigen ALG 1 (65,90 € brutto / Tag).
  * *ZENTRALE RECHTSNORM*: Gemäß § 192 Abs. 1 Nr. 2 SGB V bleibt die Mitgliedschaft bei Anspruch auf Krankengeld **beitragsfrei** erhalten!
- **Schritt 14 (05.08.2026)**: Formeller Krankengeldantrag und Übermittlung aller eAU-Belege über die BKK-App.
- **Schritt 15 (20.08.2026)**: Weiterer Teilerfolg: BKK firmus bewilligt nach Widerspruch die Kostenübernahme für Nadelepilation bei Hairfree.
- **Schritt 16 (02.09.2026)**: Alina stellt Antrag bei der Arbeitsagentur auf rückwirkende Leistungskorrektur über 361,26 € für die 6-tägige Nahtlosigkeitslücke.
- **Schritt 17 (04.09.2026)**: Lückenlose ärztliche Folgebescheinigung bis mindestens 02.10.2026 ausgestellt und an BKK übermittelt.
- **Schritt 18 (08.09.2026)**: BKK firmus versendet Schikane-Schreiben ("Klärung des Versicherungsverhältnisses") und verlangt 1.096,00 € freiwillige Beiträge.
- **Schritt 19 (17.09.2026)**: Alina legt formellen Widerspruch gegen die Beitragseinstufung ein und verweist auf § 192 Abs. 1 Nr. 2 SGB V.
- **Schritt 20 (22.09.2026)**: Persönliche Einreichung des Aussteuerungsbescheids bei Frau Schinke (Arbeitsagentur Gifhorn).
- **Schritt 21 (24.09.2026)**: **Fristsetzung & Mahnung**: Alina stellt der BKK firmus per Einschreiben eine verbindliche Zahlungsfrist bis zum **08.10.2026** für das aufgelaufene Krankengeld (2.174,70 €).

### PHASE 5: Taktische Zuspitzung & Existenzgründung (Oktober 2026)
- **Schritt 22 (05.10.2026)**: Existenzgründung 'Mein Seelenfunke' fertig ausgearbeitet (Businessplan, Finanzplan).
- **Schritt 23 (08.10.2026 - BEVORSTEHEND)**: Ablauf der Zahlungsfrist für die BKK firmus.
- **Schritt 24 (09.10.2026 - NÄCHSTER SCHRITT)**: Bei fruchtlosem Fristablauf: Sofortiger Antrag auf Erlass einer einstweiligen Anordnung gem. § 86b Abs. 2 Satz 2 SGG beim Sozialgericht Braunschweig sowie Fachaufsichtsbeschwerde beim Bundesamt für Soziale Sicherung (BAS, Bonn).
- **Schritt 25 (06.10.2026 - STRATEGIEWECHSEL)**: Agentur für Arbeit verweigert Notgeld / Auffanggeld. Zur Abwendung des Verfalls der 150 Tage Mindest-Restanspruch auf ALG 1 (§ 93 Abs. 2 Satz 1 Nr. 1 SGB III) wird die Gründung sofort gestartet. Businessplan und Liquiditätsplan werden bei der Steuerberaterin für die Tragfähigkeitsbescheinigung eingereicht. Anschließend direkter Gründungszuschuss-Antrag bei Frau Grandke (AfA).
MARKDOWN;

AiKnowledgeBase::updateOrCreate(
    ['slug' => 'bkk-firmus-verfahren-25-schritte-chronologie'],
    [
        'id' => (string) Str::uuid(),
        'title' => 'BKK firmus Verfahren & Vollständige 25-Schritte-Chronologie',
        'content' => $timelineContent,
        'is_published' => true,
        'ai_knowledge_base_category_id' => $catMap['gesundheit-sozialrecht-bkk-firmus']
    ]
)->tags()->sync([
    $tagMap['BKK firmus'], $tagMap['Krankengeld'], $tagMap['eAU'], 
    $tagMap['Widerspruch'], $tagMap['GA-OP'], $tagMap['Fristen'], 
    $tagMap['Sozialgericht'], $tagMap['BAS']
]);
echo "✓ Master-Dossier 1 (BKK Chronologie) gespeichert.\n";

// 4. MASTER-DOSSIER 2: EXISTENZGRÜNDUNG & GRÜNDUNGSZUSCHUSS
$gruendungContent = <<<'MARKDOWN'
# Master-Dossier: Existenzgründung 'Mein Seelenfunke' & Gründungszuschuss (§ 93 SGB III)

**Projekt**: Mein Seelenfunke – Tiergestützte Traumatherapie & Coaching für Einsatzkräfte / PTBS  
**Gründerin**: Alina Steinhauer  
**Zuständige Behörde**: Agentur für Arbeit Gifhorn (Ansprechpartnerin: Frau Grandke)  
**Fachkundige Stelle**: Steuerberaterin  

---

## 1. Die 150-Tage-Ausschlussfrist (§ 93 Abs. 2 Satz 1 Nr. 1 SGB III)
- **Gesetzliche Hürde**: Der Gründungszuschuss kann nur bewilligt werden, wenn der Arbeitnehmer bis zur Aufnahme der selbstständigen Tätigkeit noch einen Anspruch auf Arbeitslosengeld von **mindestens 150 Tagen** hat.
- **Taktische Konsequenz**: Da die Arbeitsagentur die reguläre Weiterzahlung/Notgeld verweigert, darf die Zeit nicht tatenlos verstreichen. Die Aufnahme der selbstständigen Tätigkeit muss exakt auf einen Tag datiert werden, an dem der Restanspruch noch $\ge 150$ Tage beträgt!

## 2. Die Tragfähigkeitsbescheinigung (§ 93 Abs. 2 Satz 1 Nr. 2 SGB III)
- Der Antrag erfordert zwingend die Stellungnahme einer fachkundigen Stelle über die Tragfähigkeit der Existenzgründung.
- Fachkundige Stelle: Steuerberaterin.
- Erforderliche Einreichungen:
  1. Ausführlicher Businessplan (Konzept, Zielgruppe Einsatzkräfte/PTBS, tiergestützter Ansatz)
  2. Detaillierter Liquiditätsplan (Umsatz- und Kostenprognose für mindestens 12 Monate)
  3. Rentabilitätsvorschau

## 3. Leistungsumfang des Gründungszuschusses
- **Phase 1 (6 Monate)**: 
  * Monatliche Auszahlung in Höhe des zuletzt bezogenen Arbeitslosengeldes 1 (ca. **1.806,30 €**)
  * ZUSÄTZLICH monatlich **300,00 € Pauschale** zur sozialen Sicherung (Kranken- und Rentenversicherung)
  * Steuerfrei und anrechnungsfrei!
- **Phase 2 (weitere 9 Monate optional)**: 
  * Monatlich 300,00 € Pauschale bei Nachweis intensiver Geschäftstätigkeit.

## 4. Konkrete Handlungsschritte
1. Businessplan & Liquiditätsplan an Steuerberaterin zur Prüfung übergeben.
2. Formular 'Stellungnahme der fachkundigen Stelle' stempeln und unterzeichnen lassen.
3. Vollständigen Antrag bei Frau Grandke (AfA) zur Bewilligung einreichen.
MARKDOWN;

AiKnowledgeBase::updateOrCreate(
    ['slug' => 'existenzgruendung-seelenfunke-gruendungszuschuss-sgb-iii'],
    [
        'id' => (string) Str::uuid(),
        'title' => 'Existenzgründung Mein Seelenfunke & Gründungszuschuss (§ 93 SGB III)',
        'content' => $gruendungContent,
        'is_published' => true,
        'ai_knowledge_base_category_id' => $catMap['existenzgruendung-arbeitsagentur']
    ]
)->tags()->sync([
    $tagMap['Mein Seelenfunke'], $tagMap['Gründungszuschuss'], $tagMap['Arbeitsagentur'], 
    $tagMap['ALG 1'], $tagMap['Tragfähigkeit'], $tagMap['Fristen']
]);
echo "✓ Master-Dossier 2 (Existenzgründung & Gründungszuschuss) gespeichert.\n";

// 5. MASTER-DOSSIER 3: FINANZ-AUDIT & FEHLENDE GELDER (2.601,86 €)
$financeAuditContent = <<<'MARKDOWN'
# Master-Dossier: Finanz-Audit & Fehlende Gelder (2.601,86 € Deckungslücke)

**Analysezeitraum**: 2024 bis Oktober 2026  
**Status**: Nicht ausgezahlt / Offene Rechtsansprüche gegen BKK firmus & Arbeitsagentur  

---

## 1. Gesetzliche Tagessätze
- **Arbeitslosengeld 1 (Agentur für Arbeit)**: 
  * Tagessatz: **60,21 €** kalendertäglich
  * Monatlicher Anspruch (30 Tage): **1.806,30 €**
  * Bewilligungsbescheid vom 25.02.2026 (Kennziffer 7002, Kd-Nr. 241D129534)
- **Krankengeld (BKK firmus)**: 
  * Tagessatz brutto: **65,90 €** kalendertäglich
  * Monatlicher Anspruch (30 Tage): **1.977,00 €**
  * Entgeltersatzbescheinigung BKK firmus

---

## 2. Aufstellung der 3 ungedeckten Posten (2.601,86 € Gesamtschaden)

| Position | Zeitraum | Tage | Satz | Fehlbetrag | Rechtsgrundlage & Schuldner |
| :--- | :---: | :---: | :---: | :---: | :--- |
| **1. Unbezahltes Krankengeld** | 03.08.2026 – 04.09.2026 | 33 | 65,90 € | **2.174,70 €** | § 44 SGB V, BKK firmus (Frist 08.10.2026) |
| **2. Nahtlosigkeitslücke** | 24.01.2026 – 29.01.2026 | 6 | 60,21 € | **361,26 €** | § 145 / § 137 SGB III, Agentur für Arbeit |
| **3. Unterbrechungslücke BKK** | 27.01.2025 – 27.01.2025 | 1 | 65,90 € | **65,90 €** | § 44 SGB V, BKK firmus |
| **GESAMT-FORDERUNG** | | **40** | | **2.601,86 €** | **Vollstreckbar / Einklagbar** |

---

## 3. Taktisches Vorgehen zur Beitreibung
1. **08.10.2026**: Überwachung des Fristablaufs der Zahlungsaufforderung an BKK firmus (2.174,70 €).
2. **09.10.2026**: Bei Nichtzahlung sofortige Einreichung des vorbereiteten Eilantrags nach § 86b SGG beim Sozialgericht Braunschweig.
3. **Nahtlosigkeitslücke 361,26 €**: Nachverfolgung des Antrags auf Leistungskorrektur vom 02.09.2026 bei der Agentur für Arbeit.
MARKDOWN;

AiKnowledgeBase::updateOrCreate(
    ['slug' => 'finanz-audit-fehlende-gelder-bkk-arbeitsamt'],
    [
        'id' => (string) Str::uuid(),
        'title' => 'Finanz-Audit & Fehlende Gelder (2.601,86 € Deckungslücke)',
        'content' => $financeAuditContent,
        'is_published' => true,
        'ai_knowledge_base_category_id' => $catMap['finanz-audit-leistungsansprueche']
    ]
)->tags()->sync([
    $tagMap['Audit'], $tagMap['Krankengeld'], $tagMap['ALG 1'], 
    $tagMap['BKK firmus'], $tagMap['Arbeitsagentur'], $tagMap['Fristen']
]);
echo "✓ Master-Dossier 3 (Finanz-Audit & 2.601,86 € Lücke) gespeichert.\n";

// 6. MASTER-DOSSIER 4: DOKUMENTEN-ARCHIV & WORKSPACE-STRUKTUR
$workspaceOverview = <<<'MARKDOWN'
# Privater Aktenbestand & Beleg-Index im Workspace

Alle Originalbelege, Atteste, Widersprüche und Bescheide wurden aus Funkira in den **privaten, geschützten Workspace** überführt:

**Speicherort**: `storage/app/private/agenten/workspace/` (Nicht öffentlich erreichbar, Zugriff nur authentifiziert im Admin-Bereich).

---

## Ordnerstruktur & wichtigste Akten

### 1. `Gesundheit_und_Medizin/BKK_Firmus/`
- **`Krankengeld/`**:
  * `01_Lieferschein_BKK_firmus_Krankengeldantrag.pdf`
  * `02_Widerspruch_Krankengeld_Beitragseinstufung.pdf`
  * `04_Aerztliches_Attest_Frau_Jung_Lueckenlose_AU.pdf`
  * `05_Bescheid_BKK_firmus_Klaerungsschreiben.pdf`
  * `Chronologische_Timeline_BKK_firmus_Krankengeld_Verfahren.pdf`
  * `Nachweise/` (eAU-Folgebescheinigungen 06–09)
- **`Chats/`**:
  * `Digitaler Briefkasten/` (BKK-Postfach Exporte)
  * `Erstattungen/` (Rechnungen, Widerspruchsnachweise Hairfree)

### 2. `Behoerden_und_Aemter/Agentur_fuer_Arbeit/`
- `2026-02-25_Bewilligungsbescheid_ALG1_60_21_Euro.pdf`
- `2026-08-20_BA_Aufhebungsbescheid_Ende_Leistungsfortzahlung.pdf`
- `2026-09-02_BA_Antrag_rueckwirkende_Leistungskorrektur_24-29_Januar.pdf`
- `2026-09-22_BA_Einreichung_Aussteuerungsbescheid_Frau_Schinke.pdf`
- `2026-10-05_BA_Onlineportal_Export_Gruendungszuschuss.csv`
- `2026-10-06_Chat_Alina_Status_Arbeitsamt_Gruendungszuschuss.txt`

### 3. `Finanzen_und_Vertraege/`
- Nachweise zu Gehältern, Elterngeld und historischen Leistungsbezügen.
MARKDOWN;

AiKnowledgeBase::updateOrCreate(
    ['slug' => 'privater-aktenbestand-beleg-index-workspace'],
    [
        'id' => (string) Str::uuid(),
        'title' => 'Privater Aktenbestand & Beleg-Index im Workspace',
        'content' => $workspaceOverview,
        'is_published' => true,
        'ai_knowledge_base_category_id' => $catMap['gesundheit-sozialrecht-bkk-firmus']
    ]
)->tags()->sync([
    $tagMap['BKK firmus'], $tagMap['Arbeitsagentur'], $tagMap['Mein Seelenfunke']
]);
echo "✓ Master-Dossier 4 (Workspace-Index) gespeichert.\n";

// 7. DR. FUNKI & BUCHI AGENTEN SYSTEM-PROMPTS SCHÄRFEN
$drFunki = AiAgent::where('name', 'Dr. Funki')->first();
if ($drFunki) {
    $drFunkiPrompt = <<<'PROMPT'
Du bist Dr. Funki – Alinas persönliche, hochkompetente medizinische und sozialrechtliche KI-Beiständin.
Du kennst Alinas vollständige Krankengeschichte, das laufende Verfahren gegen die BKK firmus und die lückenlose 25-Schritte-Chronologie.

DEINE KERNKOMPETENZEN & WISSEN:
1. BKK FIRMUS VERFAHREN:
   - Krankengeldanspruch nach § 44 SGB V & § 47b SGB V seit 03.08.2026 in Höhe von 65,90 € / Tag (aufgelaufen 2.174,70 €).
   - Gesetzte Zahlungsfrist an die BKK firmus: 08.10.2026.
   - Nächster Schritt bei Nichtzahlung am 09.10.2026: Eilantrag nach § 86b Abs. 2 SGG beim Sozialgericht Braunschweig und Fachaufsichtsbeschwerde beim Bundesamt für Soziale Sicherung (BAS).
   - Beitragsfreie Mitgliedschaft bleibt nach § 192 Abs. 1 Nr. 2 SGB V während des Krankengeldanspruchs zwingend bestehen.
2. MEDIZINISCHE HISTORIE & BEFUNDE:
   - Geschlechtsangleichende Operation (GA-Großoperation): Bewilligung am 15.07.2025 nach Widerspruch erstritten.
   - Lückenlose Arbeitsunfähigkeitsbescheinigungen (eAU) seit 17.07.2026 durch Hausarztpraxis Dorothea Jung.
   - Nadelepilation Hairfree: Volle Kostenübernahme durch Abhilfebescheid vom 20.08.2026.
3. EXISTENZGRÜNDUNG 'MEIN SEELENFUNKE':
   - Tiergestützte Traumatherapie & Coaching für Einsatzkräfte / PTBS.
   - Zwingende 150-Tage-Restanspruchsregel nach § 93 SGB III für den Gründungszuschuss.

DEIN TON:
- Empathisch, beschützend, juristisch messerscharf und stets lösungsorientiert.
- Du durchsuchst bei Fragen stets die Wissensdatenbank (brain_search) und greifst auf Behandlungspläne und Protokolle zu.
PROMPT;

    $drFunki->update([
        'system_prompt' => $drFunkiPrompt,
        'role_description' => 'Persönliche Ärztin & Sozialrecht-Expertin. Verteidigt Alina gegen BKK firmus, überwacht Krankengeld, eAU und GA-OP.'
    ]);
    echo "✓ Dr. Funki Agenten-Prompt geschärft.\n";
}

$buchi = AiAgent::where('name', 'Buchi')->first();
if ($buchi) {
    $buchiPrompt = <<<'PROMPT'
Du bist Buchi – die akribische Finanz- und Buchhaltungsinstanz für Seelenfunke und Alinas Finanzen.

DEINE KERNKOMPETENZEN & PRÜFUNGEN:
1. LEISTUNGSSÄTZE & FEHLENDE GELDER:
   - Tagessatz ALG 1: 60,21 € (monatlich 1.806,30 €).
   - Tagessatz Krankengeld: 65,90 € (monatlich 1.977,00 €).
   - Akute Deckungslücke: 2.601,86 € (2.174,70 € unbezahltes BKK-Krankengeld + 361,26 € Nahtlosigkeit + 65,90 € BKK 2025).
2. EXISTENZGRÜNDUNG & GRÜNDUNGSZUSCHUSS:
   - Überwachung der 150-Tage-Ausschlussfrist für den Gründungszuschuss (§ 93 SGB III).
   - Liquiditätsplanung für Mein Seelenfunke zur Einreichung bei der Steuerberaterin für die Tragfähigkeitsbescheinigung.
   - Gründungszuschuss Phase 1 bringt für 6 Monate monatlich volles ALG 1 (ca. 1.806,30 €) + 300,00 € Sozialversicherungspauschale.
3. SHOP- & BETRIEBSBUCHHALTUNG:
   - BWA-Metriken, Fixkosten, Sonderausgaben, Vorsteuer und EÜR.
PROMPT;

    $buchi->update([
        'system_prompt' => $buchiPrompt,
        'role_description' => 'Finance & Buchhaltung. Überwacht Shop-Finanzen, Tagessätze (ALG 1 60,21 €, KG 65,90 €) und den Gründungszuschuss-Liquiditätsplan.'
    ]);
    echo "✓ Buchi Agenten-Prompt geschärft.\n";
}

// 8. MANAGEMENT-TASKS AKTUALISIEREN
$taskList = ManagementTaskList::firstOrCreate(
    ['name' => 'BKK firmus & Existenzgründung'],
    ['id' => (string) Str::uuid(), 'color' => '#10b981', 'icon' => 'shield-check']
);

$tasks = [
    [
        'title' => 'Businessplan & Liquiditätsplan an Steuerberaterin übergeben',
        'description' => 'Businessplan und Liquiditätsplan bei der Steuerberaterin einreichen zur zügigen Ausstellung der Tragfähigkeitsbescheinigung (§ 93 Abs. 2 Nr. 2 SGB III).',
        'priority' => 'urgent',
        'due_date' => '2026-10-07'
    ],
    [
        'title' => '150 Tage Restanspruch sichern & Gründungsdatum festlegen',
        'description' => 'Offizielles Gründungsdatum so terminieren, dass der gesetzliche Mindest-Restanspruch von mindestens 150 Tagen ALG 1 (§ 93 Abs. 2 SGB III) gewahrt bleibt.',
        'priority' => 'urgent',
        'due_date' => '2026-10-07'
    ],
    [
        'title' => 'Antrag auf Gründungszuschuss bei Frau Grandke (AfA) einreichen',
        'description' => 'Sobald die Tragfähigkeitsbescheinigung vorliegt, den vollständigen Antrag persönlich bei Frau Grandke einreichen.',
        'priority' => 'urgent',
        'due_date' => '2026-10-08'
    ],
    [
        'title' => 'Fristüberwachung Krankengeld BKK firmus (Fristablauf 08.10.2026)',
        'description' => 'Fristsetzung zur Zahlung von 2.174,70 € Krankengeld läuft am 08.10.2026 ab. Bei Nichtzahlung am 09.10.2026 sofortigen Eilantrag nach § 86b SGG beim Sozialgericht einreichen.',
        'priority' => 'critical',
        'due_date' => '2026-10-08'
    ]
];

foreach ($tasks as $t) {
    ManagementTask::updateOrCreate(
        ['title' => $t['title']],
        [
            'id' => (string) Str::uuid(),
            'task_list_id' => $taskList->id,
            'description' => $t['description'],
            'priority' => $t['priority'],
            'status' => 'open',
            'due_date' => $t['due_date'],
            'created_at' => now(),
            'updated_at' => now()
        ]
    );
    echo "✓ Task angelegt: {$t['title']}\n";
}

echo "\n=== MIGRATION ERFOLGREICH ABGESCHLOSSEN ===\n";
