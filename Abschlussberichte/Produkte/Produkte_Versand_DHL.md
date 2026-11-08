# System-Dokumentation: DHL Geschäftskunden & Versandlogistik (Gifhorn, 38518)

Diese Dokumentation beschreibt die Rahmenbedingungen, Konditionen und logistischen Prozesse für den Paketversand über **DHL** bzw. alternative Versandplattformen für den Standort Gifhorn (PLZ 38518).

---

## 1. Übersicht & Zielsetzung

- **Ziel:** Rechtssicherer, kosteneffizienter und hochautomatisierter Versand der in der Manufaktur gefertigten Unikate.
- **Zielgruppe:** Interne Verwendung zur Logistikplanung des Shops *Mein Seelenfunke*.
- **Kernanforderung:** Reibungslose Übergabe der fertig verpackten Ware an den Logistikdienstleister (DHL) unter Berücksichtigung lokaler Gegebenheiten in Gifhorn.

---

## 2. DHL Geschäftskunden-Modelle

### 2.1 Direkter DHL Geschäftskundenvertrag
DHL bietet ab einem Mindestvolumen von **200 Paketen pro Jahr** (ca. 4 Pakete pro Woche) den Abschluss eines direkten Geschäftskundenvertrags an.

- **Vorteile:**
  - Individuell verhandelte Versandtarife (deutlich unter den Preisen für Privatkunden, z. B. 3,50 € – 4,50 € netto pro Standardpaket).
  - Zugang zum **DHL Geschäftskundenportal** zur Label-Erstellung, Sendungsverfolgung und Retourenabwicklung.
  - Integration von **GoGreen** (klimaneutraler Versand) und Standardhaftung bis 500 € pro Paket.
- **Einschränkung:** Sinkt die jährliche Menge dauerhaft unter 200 Sendungen, behält sich DHL das Recht vor, den Vertrag aufzulösen.

### 2.2 Alternative: Versandplattformen (für Kleinversender)
Falls das jährliche Versandvolumen unter 200 Paketen liegt, empfiehlt sich die Nutzung von Aggregatoren/Schnittstellen-Partnern wie **Sendcloud**, **SimpleSell** oder **Shipcloud**:
- Keine Mindestmengen-Verpflichtung.
- Nutzung vergünstigter DHL-Rahmentarife direkt über die Plattform.
- Vollautomatisierte Generierung von Versandlabels direkt aus den Bestelldaten des Laravel-Shops.

---

## 3. Abholkonditionen & Zeiten (Gifhorn, 38518)

Für den Abtransport der Sendungen aus der Manufaktur in Gifhorn stehen verschiedene Optionen zur Verfügung:

### 3.1 Bedarfsabholung (Flexibel)
- **Ablauf:** Die Abholung wird bei Bedarf flexibel über das Geschäftskundenportal gebucht.
- **Buchungsfrist:** Orders müssen am Vorabend (üblicherweise bis 22:00 Uhr) erteilt werden.
- **Abholzeit:** Die Abholung erfolgt am Folgetag (Montag bis Samstag) im Rahmen der regulären Zustelltour des Gifhorner DHL-Zustellers. Ein exaktes Zeitfenster ist routenabhängig (meist zwischen 10:00 und 17:00 Uhr).
- **Kosten:** Häufig ab einer bestimmten Paketmenge pro Abholung kostenfrei oder gegen eine geringe Gebühr zubuchbar.

### 3.2 Regelabholung (Fest vereinbart)
- **Ablauf:** DHL holt an fest vereinbarten Werktagen (z. B. jeden Montag, Mittwoch und Freitag) automatisch und ohne Einzelbuchung ab.
- **Kosten:** In der Regel ab ca. 3.000 Paketen/Jahr kostenfrei inbegriffen, darunter gegen eine monatliche Pauschale (wird individuell vereinbart).

### 3.3 Zusteller-Mitnahme (Kostenlose Sofort-Option)
- **Ablauf:** Der tägliche DHL-Zusteller nimmt fertig frankierte Pakete bei der regulären Zustellung an der Adresse der Manufaktur direkt mit.
- **Kapazität:** Bis zu 10–20 Pakete pro Tag sind in der Regel problemlos möglich, sofern im Zustellfahrzeug noch Ladekapazität vorhanden ist.
- **Kosten:** Zu 100 % kostenfrei.

### 3.4 Eigene Abgabe in Gifhorn
Ergänzend können Pakete jederzeit an folgenden Stellen abgegeben werden:
- **DHL Packstationen** (24/7 zugänglich).
- **DHL Paketshops / Postfilialen** im Stadtgebiet Gifhorn (z. B. Steinweg, Limbergstraße etc.) zu den regulären Öffnungszeiten.

---

## 4. Technische Anbindung im E-Commerce Portal

Der Laravel-Shop kann über entsprechende Module (z. B. native DHL-API oder Shipcloud-Wrapper) angebunden werden:
- Automatischer Export der Lieferadresse bei Statusänderung einer Bestellung auf "bereit zum Versand".
- Rückübermittlung der **Tracking-Nummer** an den Kunden per E-Mail nach erfolgreicher Label-Generierung.

---

## 5. Funkenwerk Print-Bridge & MUNBYN ITPP941AP WLAN-Labeldrucker

Zur vollautomatisierten Label-Ausgabe direkt aus dem Web-Shop (`Local`, `Stage` oder `Live`) in das lokale Manufaktur-WLAN ist der Thermo-Etikettendrucker **MUNBYN ITPP941AP** über die **Funkenwerk Desktop Suite** angebunden:

### 5.1 Hardware- & Netzwerk-Stammdaten
- **Modell:** `MUNBYN ITPP941AP` (203 DPI Direkt-Thermodrucker, Format `100 × 150 mm` / `4 × 6 Zoll` = `812 × 1218 px`)
- **Feste IPv4 (FRITZ!Box):** `192.168.188.149` (`ITPP941AP.fritz.box`)
- **MAC-Adresse:** `F0:23:AE:7D:51:35` | **Seriennummer:** `941AP261000423`
- **Protokolle:**
  - **IPP 2.0 / AirPrint (`Port 631`, `/ipp/print`):** Primärer Druckkanal (`image/jpeg`, `na_index-4x6_4x6in`, `Get-Printer-Attributes` Live-Status, `Identify-Printer` akustischer/optischer Signalton).
  - **RAW Thermal Socket (`Port 9100`):** Automatischer TSPL-Fallback (`SIZE 100 mm,150 mm` + 1-Bit `BITMAP`).

### 5.2 Ende-zu-Ende-Druckworkflow (Shop -> Funkenwerk -> MUNBYN)
1. **Shop-Trigger (`/admin/orders` -> `OrderOverview`):**
   - **1-Klick in der Bestelltabelle:** Drucker-Icon direkt in der Aktionsspalte jeder Bestellung (`printOrderLabelViaFunkenwerk`).
   - **Bestelldetails-Header:** Druck-Button direkt neben jedem DHL-Label (`printDhlLabelViaFunkenwerk`) bzw. `Drucken`-Button für Bestellungen ohne bisheriges DHL-Label.
   - **Auto-Enqueue & Stapeldruck:** Beim Klick auf `DHL Labels generieren` werden alle neu erzeugten Labels automatisch in die Funkenwerk-Queue eingereiht; zusätzlich steht oben der Sammel-Button `Funkenwerk-Druck` (`printMergedLabelsViaFunkenwerk`) bereit.
2. **REST-API & Persistente Print-Queue (`FunkenwerkPrintService`):**
   - Speichert Druckaufträge inkl. Base64-kodiertem DHL-PDF (`pdf_base64`) sowie strukturierten Bestell-, Adress- und Artikeldaten in `storage/app/print_queue/jobs.json`.
   - Endpunkte (`routes/api/orders.php`):
     - `GET /api/printer/jobs` (liefert offene Aufträge + Historie)
     - `POST /api/printer/jobs/test` (erstellt ein Test-Label)
     - `POST /api/printer/jobs/{jobId}/ack` (bestätigt erfolgreichen Druck oder meldet Fehler)
     - `POST /api/printer/jobs/{jobId}/requeue` & `POST /api/printer/jobs/clear`
     - `POST /api/shop/orders/{id}/print-label`
3. **Funkenwerk Desktop Suite (`funkenwerk/SmartHomeEngine.cs` & `MainWindow.xaml.cs`):**
   - Pollt im Hintergrund alle 3 Sekunden `GET /api/printer/jobs?include_pdf=1` von der aktiven Shop-Umgebung (`Live`, `Stage` oder `Lokal`).
   - **DHL-PDF-Konvertierung:** Wandelt vorhandene DHL-PDFs via PyMuPDF (`fitz`) verlustfrei in ein 203-DPI Graustufen-JPEG (`812 × 1218 px`) um.
   - **Natives Seelenfunke-Versandlabel:** Falls noch kein DHL-PDF existiert, rendert Funkenwerk nativ ein scharfes `100 × 150 mm` Seelenfunke-Versand- & Packlabel (Absender Manufaktur Gifhorn, Empfängeradresse, Bestellnummer, QR-/Tracking-Referenz und Packliste).
   - **Smarthome-Steuerung:** Im Bereich **Smarthome -> WLAN-Labeldrucker (Shop-Bridge)** lassen sich Live-Status (`Bereit`), Auto-Print-Schalter, Drucker-Ping (`Signalton`), `Test-Label` und die Auftrags-Queue in Echtzeit verwalten.

