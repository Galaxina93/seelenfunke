# System-Dokumentation: Leitung / Routine (Tagesablauf-Planer)

Das Routine-Modul dient der Pflege und Strukturierung des täglichen Arbeits- und Lebensablaufs. Es bietet der Leitung eine visuelle Zeitachse, um wiederkehrende tägliche Aufgaben, Meetings, Pausen und Workflows zeitlich festzulegen.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Strukturierung des Arbeitstages in feste Zeitblöcke (z. B. "E-Mail-Posteingang bearbeiten", "Team-Meeting", "Qualitätskontrolle").
- **Vorteil:** Erhöhte Übersicht und Standardisierung der täglichen Arbeitsabläufe der Unternehmensleitung.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ManagementRoutine`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementRoutine.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)

### 2.2 Datenbank-Modell
- **`App\Models\Management\ManagementDayRoutine` (alias `RoutineModel`):**
  Speichert die einzelnen Routine-Zeitblöcke.
  - `start_time`: Uhrzeit des Beginns (z. B. `08:30:00`).
  - `title`: Name des Routine-Schritts.
  - `message`: Optionale Beschreibung oder Checkliste für diesen Block.
  - `duration_minutes`: Dauer in Minuten (wird zur visuellen Skalierung genutzt).
  - `type`: Kategorie des Eintrags (`general`, `work`, `break`, `meeting`, `sport` etc.).
  - `is_active`: Boolean zur Aktivierung/Deaktivierung.

---

## 3. Kernfunktionen & UI-Verbindung

- **Sortierung:** Die Routinen werden im Livewire-Controller automatisch chronologisch nach `start_time` aufsteigend sortiert und gerendert.
- **Interaktiver Editor:** Ermöglicht das Erstellen und Bearbeiten einzelner Zeitblöcke über ein modales oder Inline-Formular. Die Uhrzeit wird komfortabel über ein Standard-Zeit-Eingabefeld erfasst.
- **UUID-Primärschlüssel:** Neue Routinen erhalten bei der Erstellung automatisch eine UUID v4 (`Str::uuid()`), um Datenkonsistenz zu gewährleisten.
- **Visuelle Zeitachse:** Im Frontend werden die Routineblöcke basierend auf ihrer `duration_minutes` proportional auf einer vertikalen oder horizontalen Achse dargestellt, um Lücken oder Überschneidungen im Tagesablauf sofort zu visualisieren.
