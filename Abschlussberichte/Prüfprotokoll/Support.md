# Prüfprotokoll – Support

Support -> Unterseite ([Auswertung](http://127.0.0.1/admin/support/analytics)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Support -> Unterseite ([Kunden-Chats](http://127.0.0.1/admin/support-chats)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Support -> Unterseite ([Tickets](http://127.0.0.1/admin/support-tickets)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Support -> Unterseite ([Kontaktformular](http://127.0.0.1/admin/support-contact-form)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Support -> Unterseite ([Telefonie](http://127.0.0.1/admin/support/telephony)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)

---

## Physischer Chrome-E2E-Klicktest (Remote Debugging)

| Wo (Bereich / Route) | Welcher Link / Button / Funktion | Ergebnis | Wann |
| :--- | :--- | :--- | :--- |
| **Support -> Auswertung** (`http://127.0.0.1/admin/support/analytics`) | **Zeitraum-Dropdown (`Letzte 7 Tage`, `Letzte 30 Tage`, `Letzte 90 Tage`, `Dieses Jahr`), KPI-Karten & Charts** | **100% GRÜN** – Zeitraum-Auswahl physisch getestet; alle 6 KPIs und 3 Charts (`Aufkommen im Zeitverlauf`, `Themen-Schwerpunkte`, `Top genutzte KI-Werkzeuge`) rendern fehlerfrei. | 04.11.2026, 15:06 Uhr |
| **Support -> Kunden-Chats** (`http://127.0.0.1/admin/support-chats`) | **Accordion `Auswertungen im Detail anzeigen`, Status-Filter (`Alle Status`, `Hilfe benötigt`, `KI Aktiv`, `Gelöst`), Bewertungs-Filter** | **100% GRÜN** – Detail-Accordion physisch auf- und zugeklappt, Filter-Steuerung geprüft. | 04.11.2026, 15:06 Uhr |
| **Support -> Tickets** (`http://127.0.0.1/admin/support-tickets`) | **Ansichts-Umschalter `Split` ↔ `Tabelle`, Status-Filter `ALLE`, `OFFEN`, `ANTWORT`, `ZU`** | **100% GRÜN** – Alle 4 Status-Buttons und der Umschalter zwischen Split-View und Tabellen-Ansicht physisch geklickt. | 04.11.2026, 15:06 Uhr |
| **Support -> Kontaktformular** (`http://127.0.0.1/admin/support-contact-form`) | **Suchfeld, Status-Filter (`Alle Status`, `Neu (Ungelesen)`, `In Bearbeitung`, `Erledigt`)** | **100% GRÜN** – Alle Filterelemente und Split-Workspace-Komponenten geprüft. | 04.11.2026, 15:07 Uhr |
| **Support -> Telefonie** (`http://127.0.0.1/admin/support/telephony`) | **Tabs `Dashboard` ↔ `Einstellungen`, Webhook-Konfiguration & Speichern-Button** | **100% GRÜN** – Zwischen `Dashboard` und `Einstellungen` physisch gewechselt, KI-Prompt- und Webhook-Konfiguration geprüft. | 04.11.2026, 15:07 Uhr |
