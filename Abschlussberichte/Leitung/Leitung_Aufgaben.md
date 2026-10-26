# System-Dokumentation: Leitung / Aufgaben (Task-Manager)

Der Aufgaben-Manager (Task-Manager) ist das primäre Werkzeug zur operativen Steuerung von Aufgaben und Projekten. Er ermöglicht die Organisation von Aufgaben in separaten Listen, die Priorisierung, das Erstellen von Unteraufgaben sowie eine Archivierung.

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Strukturierung von Projekten und Einzelaufgaben in anpassbaren Listen.
- **Hierarchische Struktur:** Unterstützung von Hauptaufgaben und beliebig vielen untergeordneten Teilaufgaben (Subtasks).
- **Priorisierung:** Schnelle Einteilung in Dringlichkeitsstufen zur effizienten Abarbeitung.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ManagementTask`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementTask.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)

### 2.2 Datenbank-Modelle
- **`App\Models\Management\ManagementTaskList`:**
  Repräsentiert die übergeordneten Aufgabenlisten (z. B. "Marketing", "Einkauf", "Private To-Dos").
  - `name`: Name der Liste.
  - `icon`: Icon zur visuellen Kennzeichnung (z. B. `bookmark`, `inbox`).
  - `color`: CSS-Farbcode (Standard-Gold: `#C5A059`).
  - `position`: Sortierreihenfolge der Listen.
  - `is_archived`: Boolean-Archivierungsstatus.
- **`App\Models\Management\ManagementTask` (alias `TaskModel`):**
  Repräsentiert die einzelne Aufgabe bzw. Unteraufgabe.
  - `task_list_id`: Referenz auf die zugehörige Liste.
  - `parent_id`: Fremdschlüssel auf dieselbe Tabelle (`management_tasks`), um Unteraufgaben abzubilden.
  - `title`: Titel der Aufgabe.
  - `priority`: Dringlichkeitsstufe. Deutschsprachige Standardwerte: `niedrig`, `mittel`, `hoch`.
  - `is_completed`: Erledigt-Status.
  - `is_archived`: Archivierungsstatus.
  - `position`: Sortierreihenfolge der Aufgaben innerhalb einer Liste.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Prioritäts-Migration & Listensteuerung
- Beim Start (`mount`) führt die Komponente eine einmalige Migration durch, um alte englischsprachige Prioritäten (`low`, `medium`, `high`) in die deutschen Entsprechungen zu überführen.
- Es wird standardmäßig die älteste erstellte Liste ausgewählt, um dem Nutzer sofort Inhalte anzuzeigen.
- Aufgabenlisten können per Drag & Drop geordnet werden, was per `updateListOrder` und `updateTaskOrder` die Positionen in der Datenbank speichert.

### 3.2 Erstellung von Aufgaben und Unteraufgaben
- **Schnellerstellung:** Neue Aufgaben können direkt über eine Eingabezeile in der aktiven Liste hinzugefügt werden.
- **Unteraufgaben:** Zu jeder Hauptaufgabe können per Eingabefeld Unteraufgaben hinzugefügt werden. Diese verweisen über `parent_id` auf die Hauptaufgabe.
- **Beförderung (`promoteToTask`):** Eine Unteraufgabe kann durch Entfernen des `parent_id` zu einer vollwertigen Hauptaufgabe befördert werden.

### 3.3 Sortierung und Archivierung
- **Sortierlogik:** Aufgaben werden standardmäßig nach `is_completed` (offene zuerst), Priorität (`hoch` -> `mittel` -> `niedrig`) und Position sortiert.
- **Archiv-Modus (`toggleArchiveMode`):** Ermöglicht das Ausblenden erledigter oder nicht mehr aktiver Listen und Aufgaben aus der Standardansicht. Sie können über den Archivfilter eingesehen und bei Bedarf reaktiviert werden.
