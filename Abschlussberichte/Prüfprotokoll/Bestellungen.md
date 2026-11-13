# Prüfprotokoll – Bestellungen

Bestellungen -> Unterseite ([Auswertung](http://127.0.0.1/admin/orders/analytics)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Bestellungen -> Unterseite ([Bestellungen](http://127.0.0.1/admin/orders)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Bestellungen -> Unterseite ([Warenkörbe](http://127.0.0.1/admin/shopping-carts)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Bestellungen -> Unterseite ([Angebote](http://127.0.0.1/admin/quote-requests)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)
Bestellungen -> Unterseite ([Widerruf](http://127.0.0.1/admin/widerruf)) -> Alle Dateien & UI-Interaktionen geprüft / 100% Grün (Geprüft am: 04.11.2026)

---

## Physischer Chrome-E2E-Klicktest (Remote Debugging)

| Wo (Bereich / Route) | Welcher Link / Button / Funktion | Ergebnis | Wann |
| :--- | :--- | :--- | :--- |
| **Bestellungen -> Auswertung** (`http://127.0.0.1/admin/orders/analytics`) | **Zeitraum-Filter, 8 KPI-Karten & 9 Charts (`Umsatz & Bestellungen`, `Zahlungsarten`, `Bestellstatus`, `Kunden-Geografie` etc.)** | **100% GRÜN** – Alle 9 Charts und KPIs laden fehlerfrei. | 04.11.2026, 15:13 Uhr |
| **Bestellungen -> Bestellungen** (`http://127.0.0.1/admin/orders`) | **Bestellungs-Detail-Drawer (`ORD-2026-OW39DR`), Rechnungs-PDF-Links (`RE-2026-10006` etc.), `SAMMEL-DRUCK`, `ALS VERSENDET MARKIEREN`, `Bestellung unwiderruflich löschen`** | **100% GRÜN** – Detail-Drawer physisch geöffnet (`ORD-2026-OW39DR`), Laser-Gravurdaten, DHL-Label-Erstellung, Rechnungs-PDFs sowie GoBD-Löschschutz (`canBePermanentlyDeleted()` + rückstandsloses `forceDelete()` inkl. Upload-/Snapshot-Bereinigung bei unbezahlten Abbruch-/Testbestellungen) geprüft. | 13.11.2026, 11:10 Uhr |
| **Bestellungen -> Warenkörbe** (`http://127.0.0.1/admin/shopping-carts`) | **Button `Ampeln konfigurieren`, Status-Filter (`Alle Warenkörbe`, `Noch aktiv`, `Kritisch`, `Abgebrochen`), Zeitstempel-Anzeige** | **GEFIXT & 100% GRÜN** – Doppeltes `"VOR VOR 12 STUNDEN"` in `order-shopping-carts.blade.php` behoben (`diffForHumans()` liefert bereits `"vor 12 Stunden"`). Ampel-Schwellenwert-Panel physisch geöffnet. | 04.11.2026, 15:13 Uhr |
| **Bestellungen -> Angebote** (`http://127.0.0.1/admin/quote-requests`) | **Angebots-Detailansicht (`AN-2026-00PEX`), Buttons `ABLEHNEN`, `RECHNUNG`, `ZAHLUNGSLINK`, `📋 ALLE WERTE KOPIEREN`** | **100% GRÜN** – Detail-Modal physisch geöffnet, Express-Konfigurator-Werte und Angebots-Aktionen geprüft und geschlossen. | 04.11.2026, 15:14 Uhr |
| **Bestellungen -> Widerruf** (`http://127.0.0.1/admin/widerruf`) | **Suchfeld, Status-Filter (`Offen / Neu`, `Abgewickelt`, `Abgelehnt`, `Alle Anfragen`) & § 356a BGB Widerrufs-Tabelle** | **100% GRÜN** – Vollständig geladen und geprüft. | 04.11.2026, 15:14 Uhr |
