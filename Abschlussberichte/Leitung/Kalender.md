# System-Dokumentation: Leitung / Kalender

Das Kalender-Modul ist die zentrale Schnittstelle zur Terminplanung und Synchronisation von Ereignissen. Es bietet umfassende Ansichten für Tage, Wochen, Monate und Jahre, verwaltet wiederkehrende Termine und bietet eine Importschnittstelle für standardisierte ICS-Kalenderdateien (z. B. Müllkalender oder externe Buchungen).

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Zentrale Verwaltung von geschäftlichen und betrieblichen Terminen (Meetings, Kundengespräche, Urlaubsplanung) sowie wiederkehrenden Ereignissen.
- **Wiederkehrende Termine:** Dynamische Berechnung von Serienterminen (täglich, wöchentlich, monatlich, jährlich) ohne übermäßige Datenbankbelastung.
- **ICS-Import:** Direkter Parser für ICS-Dateien zur nahtlosen Übernahme externer Kalenderdaten mit automatischer Kategorisierung.

---

## 2. Technische System-Architektur

### 2.1 Livewire-Komponente
- **Klasse:** [`ManagementCalender`](file:///wsl.localhost/Ubuntu/home/ubuntuxina/meine-projekte/seelenfunke/app/Livewire/Shop/Management/ManagementCalender.php)
- **Layout:** `components.layouts.backend_layout` (Department-Theme: `Leitung`)
- **Traits:** `WithFileUploads` (für ICS-Datei-Uploads)

### 2.2 Datenbank-Modell
- **`App\Models\Management\ManagementCalendarEvent`:**
  Repräsentiert ein einzelnes Kalenderereignis oder die Vorlage für einen Serientermin.
  - `title`: Titel des Termins.
  - `start_date`: Beginn (DateTime).
  - `end_date`: Ende (DateTime).
  - `is_all_day`: Flag für ganztägige Termine.
  - `category`: Kategorie zur farblichen/visuellen Filterung (z. B. `general`, `meeting`, `birthday`, `call`, Abfallarten etc.).
  - `description`: Beschreibungstext oder Notizen.
  - `priority`: Dringlichkeitsstufe (`low`, `medium`, `high`).
  - `recurrence`: Wiederholungsintervall (`daily`, `weekly`, `monthly`, `yearly` oder `null`).
  - `recurrence_end_date`: Enddatum der Terminserie.
  - `reminder_minutes`: Vorlaufzeit für Erinnerungsbenachrichtigungen (in Minuten).
  - `ics_uid`: Eindeutige ID aus ICS-Importen, um Duplikate bei erneuten Importen zu verhindern.

---

## 3. Kernfunktionen & Datenfluss

### 3.1 Ansichten & Navigation
- Der Kalender unterstützt verschiedene Darstellungsmodi (`view`):
  - `month`: Klassische Monatsübersicht im Grid-Format.
  - `week`: Wöchentliche Spaltenansicht.
  - `day`: Detailansicht eines einzelnen Tages.
  - `year`: Jahresübersicht mit farblicher Markierung von Tagen mit Terminen.
  - `multi-week`: Zeigt 4 aufeinanderfolgende Wochen (ideal für rollierende Planungen).
  - `list`: Chronologische Liste aller Termine des aktuellen Monats.
- Über die Methoden `next()`, `prev()` und `today()` wird das aktive Datum (`currentDate`) entsprechend des gewählten Darstellungsmodus verschoben.

### 3.2 Serientermin-Berechnung (Recurrence Engine)
Um die Datenbank klein zu halten, werden wiederkehrende Termine dynamisch zur Laufzeit berechnet:
1. In der Eigenschaft `getEventsProperty()` werden alle Standardtermine im aktuellen Zeitraum geladen.
2. Zusätzlich werden alle Termine mit aktivem `recurrence` abgefragt.
3. Die Komponente simuliert in einer Schleife ab dem Startdatum des Serientermins (`start_date`) alle Intervalle bis zum Ende des aktuellen Darstellungszeitraums.
4. Für jeden Treffer (der vor dem optionalen `recurrence_end_date` liegt) wird eine temporäre Instanz des Modells via `replicate()` erzeugt und mit dem berechneten Datum versehen.

### 3.3 ICS-Import & Auto-Kategorisierung
- Der Nutzer kann über ein Modal eine `.ics`-Datei hochladen.
- Die Methode `importEvents()` liest die Datei ein und übergibt den Inhalt an `parseIcs()`.
- **Parser-Logik:**
  - Der Parser teilt die Datei in `VEVENT`-Blöcke.
  - Er extrahiert `UID`, `SUMMARY`, `DTSTART`, `DTEND` und `TRIGGER` (für Erinnerungen).
  - Zeitzonen-Parameter (`TZID`) und ganztägige Ereignisse (8-stellige Datumsangaben) werden korrekt verarbeitet.
- **Automatische Kategorisierung (`detectCategory`):**
  Anhand von Schlüsselwörtern im Titel des Termins (z. B. "Restmüll", "Besprechung", "Geburtstag", "Urlaub") wird der Termin automatisch einer passenden Kategorie zugeordnet.
- **Duplikatsvermeidung:** Über das Feld `ics_uid` wird ein `updateOrCreate()` ausgeführt, sodass aktualisierte Termine überschrieben statt verdoppelt werden.
