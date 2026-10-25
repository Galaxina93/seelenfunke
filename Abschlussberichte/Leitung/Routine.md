# System-Dokumentation: Leitung / Routine (Tagesablauf-Planer & Habit-Tracker)

Das Routine-Modul unter `/admin/routine` dient der Pflege, Strukturierung und täglichen Selbstkontrolle des Arbeits- und Lebensablaufs. Es kombiniert die klassische visuelle **Tagesroutine (Bio-Rhythmus Timeline)** mit einer interaktiven **Habit-Tracker-Matrix**, einer **Wöchentlichen / Monatlichen / Jährlichen Auswertung**, **logisch berechneten System-Tipps** sowie einem **4-stufigen Funki-Bewertungssystem**.

---

## 1. Übersicht & Kernlogik

- **Mo–Fr Fokus & Heiliges Wochenende:**
  - Der Leistungsfokus liegt strikt auf den Werktagen **Montag bis Freitag (5 Arbeitstage pro Woche)**.
  - **Samstag & Sonntag** gelten als **Heilige Freizeit (Freie Gestaltung)**: Sie fließen nicht als Pflicht-Soll in die Quoten ein und unterbrechen keine laufenden Serien (`🔥`).
- **Tägliches Abhaken (Flexibel & Schnell):**
  - Jeder Routine-Eintrag kann mit einem Klick direkt in der **Routine-Matrix** oder in der klassischen **Tagesroutine-Timeline** abgehakt werden.
  - Zusätzlich lassen sich einzelne Unterschritte (`ManagementDayRoutineStep`) als Checkliste aufklappen und einzeln abhaken. Sobald alle Schritte erledigt sind, gilt der gesamte Block automatisch als abgeschlossen.
- **Fair-Score vs. Strenger Zeitraum-Score (Intelligente Nachjustierung):**
  - Im **Fair-Score-Modus (Standard)** zählen für die Bewertung nur die bisher fälligen Werktage (`Mo–Fr` bis einschließlich heute) sowie am heutigen Tag nur Blöcke, deren Startzeit bereits erreicht wurde (oder die bereits erledigt sind). So wird man montags morgens um 08:00 Uhr nicht fälschlicherweise mit 15% bewertet, obwohl man zu 100% im Plan liegt.
  - Per Klick lässt sich jederzeit auf den **Strengen Modus** (Messung gegen alle Werktage des gesamten Zeitraums) umschalten.

---

## 2. Funki-Bewertungssystem (4 Stufen)

Anhand der erreichten Effektivitäts-Quote bewertet Funki den aktuellen Status visuell mit eigens generierten Charakter-Grafiken (`public/shop/projekt/funki/routine/`):

1. **0% – 25% (`funki_0_25.webp` – Sprechblase: *"DU LOSER!"*):**
   - Kritischer Disziplin-Einbruch, weniger als ein Viertel der fälligen Routine geschafft.
2. **26% – 50% (`funki_25_50.webp` – Sprechblase: *"KEINE AUSREDEN!"*):**
   - Ausreden-Zone, weniger als die Hälfte erreicht.
3. **51% – 75% (`funki_50_75.webp` – Sprechblase: *"DA GEHT MEHR!"*):**
   - Solides Fundament, aber es fehlen noch die letzten Prozente zur Konstanz.
4. **76% – 100% (`funki_75_100.webp` – Sprechblase: *"CHAMPION!"*):**
   - Elite-Disziplin über die Arbeitswoche hinweg.
5. **Krankheits- & Genesungs-Modus (`funki_sick.webp` – Sprechblase: *"GUTE BESSERUNG!"*):**
   - Jeder Tag kann per 1-Klick (`🤒 Krank`) als Krankheitstag markiert werden.
   - **Medizin-Funki** wünscht gute Besserung, die laufenden `🔥` Serien werden eingefroren (Streak-Schutz) und Krankheitstage werden aus dem Pflicht-Soll herausgerechnet, sodass die Effektivitäts-Quote nicht sinkt, während die Anzahl der Krankheitstage separat getrackt wird.

---

## 3. Technische System-Architektur

### 3.1 Livewire-Komponente & View
- **Klasse:** [`ManagementRoutine.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementRoutine.php)
- **Blade-View:** [`management-routine.blade.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/resources/views/livewire/shop/management/management-routine.blade.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)

### 3.2 Datenbank-Modelle
- **[`ManagementDayRoutine.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Management/ManagementDayRoutine.php):**
  Speichert die einzelnen Routine-Zeitblöcke (`start_time`, `title`, `message`, `duration_minutes`, `type`, `days_of_week`, `is_active`).
- **[`ManagementDayRoutineStep.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Management/ManagementDayRoutineStep.php):**
  Speichert die einzelnen Unterschritte eines Routine-Blocks (`day_routine_id`, `title`, `position`, `duration_minutes`).
- **[`ManagementDayRoutineLog.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Models/Management/ManagementDayRoutineLog.php):**
  Speichert das tägliche Tracking pro Routine und Datum (`day_routine_id`, `date`, `is_completed`, `completed_steps`, `completed_at`, `note`).

---

## 4. Auswertungen, Charts & System-Tipps

- **Verlaufskurve (SVG Area/Line Chart):** Zeigt direkt oberhalb der Matrix die tägliche bzw. monatliche Erfüllungsquote im Zeitverlauf.
- **Matrix-Kennzahlen pro Zeile:** Zeigt rechts neben jeder Routine die Quote (`X/Y` & `%`), die aktuelle Werktags-Serie (`🔥`) und die gesammelten Abschlüsse im Zeitraum (`⭐`).
- **Wochentags-Disziplin & Lebensbereiche:** Visualisiert die Performance getrennt nach Wochentagen (`Mo` bis `Fr`) sowie nach Routine-Kategorien (`Morgen- & Abendpflege`, `Sport & Workout`, `Deep Work & Business`, `Ernährung`, `Orga & Freizeit`, `Nachtruhe`).
- **Smart Coach Engine:** Berechnet automatisch 3 datenbasierte System-Tipps (Größter Hebel/Schwachstelle, Stärkster Konstanz-Anker, Tageszeit-/Bio-Rhythmus-Analyse).

---

## 5. KI-Agenten Fähigkeiten (`AiRoutineFuncs`)

Über [`AiRoutineFuncs.php`](file://wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Services/AI/Functions/AiRoutineFuncs.php) stehen den internen KI-Agenten (insb. **Funkira** und **Dr. Funki**, sowohl im Chat als auch im Echtzeit-Sprachmodus **Gemini Live**) 9 spezialisierte Werkzeuge zur Verfügung:

1. **`routine_get_day_routines`:** Ruft die Bio-Rhythmus-Timeline inkl. Unterschritten, heutigem/gewähltem Abhak-Status (`is_completed`), Krankheitsstatus (`is_sick`), `🔥` Serien, aktivem Zeitfenster (`past`/`current`/`future`) und Funki-Tagesstatus ab.
2. **`routine_get_evaluation`:** Liefert die vollständige Wochen-, Monats- oder Jahresauswertung (Mo–Fr Fokus, Sa/So Heilige Freizeit) inkl. Funki-Bewertungsstufe (`0–25%`, `26–50%`, `51–75%`, `76–100%`, `Medizin-Funki`), Quoten pro Routine, Wochentags-/Kategorie-Statistiken und System-Coaching-Tipps.
3. **`routine_log_progress`:** Hakt einzelne oder mehrere Routinen (per Name, Uhrzeit oder ID) oder alle fälligen Routinen eines Tages ab bzw. setzt sie zurück (inkl. optionaler Tagesnotiz und Live-UI-Refresh).
4. **`routine_log_step`:** Hakt einzelne Unterschritte (Steps) innerhalb einer Routine ab und markiert die Hauptroutine bei vollständiger Erledigung automatisch als abgeschlossen.
5. **`routine_set_sick_day`:** Aktiviert oder deaktiviert den Krankheits- und Genesungsmodus (`Medizin-Funki: GUTE BESSERUNG!`) für einzelne Tage oder Datumszeiträume bei vollem Serien- und Score-Schutz.
6. **`routine_create`:** Legt einen neuen Routine-Zeitblock inklusive Kategorie, Wochentags-Gültigkeit und Unterschritten an.
7. **`routine_update`:** Bearbeitet Titel, Startzeit, Dauer, Kategorie, Wochentage, Aktiv-Status oder Unterschritte einer bestehenden Routine.
8. **`routine_delete`:** Löscht eine Routine samt Unterschritten und Historie.
9. **`routine_manage_steps`:** Fügt Unterschritte einer bestehenden Routine gezielt hinzu (`add`), ändert sie (`update`) oder entfernt sie (`delete`).
